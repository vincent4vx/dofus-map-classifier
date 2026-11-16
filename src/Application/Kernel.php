<?php

declare(strict_types=1);

namespace MapClassifier\Application;

use MapClassifier\Infrastructure\Export\ExportFormat;
use MapClassifier\Infrastructure\OpenAI\ConcurrentResponsesClient;
use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Classification\Command\ClassifyRunCommand;
use MapClassifier\Map\Classification\Command\ExportCommand;
use MapClassifier\Map\Classification\Command\MapsRenderCommand;
use MapClassifier\Map\Classification\Export\HtmlMapExportFormat;
use MapClassifier\Map\Classification\Export\JsonMapExportFormat;
use MapClassifier\Map\Classification\Model\MapExport;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Classification\Service\ClassificationPrompt;
use MapClassifier\Map\Classification\Service\ImageEncoder;
use MapClassifier\Map\Classification\Service\MapExporter;
use MapClassifier\Map\Classification\Service\MapRenderService;
use MapClassifier\Map\Classification\Service\MapResultParser;
use MapClassifier\Map\Classification\Service\MapsToClassify;
use MapClassifier\Map\Classification\Service\SyncClassifier;
use MapClassifier\Map\Command\ClientDownloadCommand;
use MapClassifier\Map\Command\KeysDownloadCommand;
use MapClassifier\Map\Repository\MapFileRepository;
use MapClassifier\Map\Repository\MapKeyRepository;
use MapClassifier\Map\Service\ClientDownloader;
use MapClassifier\Map\Service\MapImageRenderer;
use MapClassifier\Map\Service\MapKeyDownloader;
use MapClassifier\Sprite\Classification\Command\ObjectsClassifyCommand;
use MapClassifier\Sprite\Classification\Command\ObjectsExportCommand;
use MapClassifier\Sprite\Classification\Export\HtmlObjectExportFormat;
use MapClassifier\Sprite\Classification\Export\JsonObjectExportFormat;
use MapClassifier\Sprite\Classification\Model\Export\ObjectExport;
use MapClassifier\Sprite\Classification\Repository\ObjectTypeRepository;
use MapClassifier\Sprite\Classification\Service\ObjectExporter;
use MapClassifier\Sprite\Classification\Service\ObjectTypeClassifier;
use MapClassifier\Sprite\Classification\Service\ObjectTypePrompt;
use MapClassifier\Sprite\Classification\Service\SpritesToClassify;
use MapClassifier\Sprite\Command\ObjectsAnalyzeCommand;
use MapClassifier\Sprite\Command\ObjectsSpritesCommand;
use MapClassifier\Sprite\Repository\InteractiveObjectRepository;
use MapClassifier\Sprite\Repository\ObjectProfileRepository;
use MapClassifier\Sprite\Repository\SpriteImageRepository;
use MapClassifier\Sprite\Service\MapObjectExtractor;
use MapClassifier\Sprite\Service\SpriteExporter;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Services wiring
 */
final class Kernel
{
    public HttpClientInterface $httpClient {
        get => $this->httpClient ??= HttpClient::create(['timeout' => 120]);
    }

    public MapFileRepository $maps {
        get => $this->maps ??= new MapFileRepository($this->config->mapsDir);
    }

    public MapKeyRepository $keys {
        get => $this->keys ??= new MapKeyRepository($this->config->keysDir);
    }

    public MapImageRenderer $renderer {
        get => $this->renderer ??= new MapImageRenderer($this->config->dofusPath, $this->config->cacheDir, $this->keys);
    }

    public MapResultRepository $results {
        get => $this->results ??= new MapResultRepository($this->config->resultsDir);
    }

    public MapsToClassify $mapsToClassify {
        get => $this->mapsToClassify ??= new MapsToClassify($this->maps, $this->results, $this->config->rendersDir);
    }

    public InteractiveObjectRepository $interactiveObjects {
        get => $this->interactiveObjects ??= new InteractiveObjectRepository($this->config->langDir, $this->config->lang);
    }

    public MapLocationRepository $locations {
        get => $this->locations ??= new MapLocationRepository($this->config->langDir, $this->config->lang);
    }

    public SpriteImageRepository $spriteImages {
        get => $this->spriteImages ??= new SpriteImageRepository($this->config->spritesDir);
    }

    public ObjectProfileRepository $objectProfiles {
        get => $this->objectProfiles ??= new ObjectProfileRepository($this->config->spriteProfilesFile);
    }

    public SpritesToClassify $spritesToClassify {
        get => $this->spritesToClassify ??= new SpritesToClassify($this->objectTypes, $this->spriteImages);
    }

    public ObjectTypeRepository $objectTypes {
        get => $this->objectTypes ??= new ObjectTypeRepository($this->config->objectTypesDir);
    }

    public function __construct(
        public readonly Config $config,
        ?HttpClientInterface $httpClient = null,
    ) {
        if ($httpClient) {
            $this->httpClient = $httpClient;
        }
    }

    public function syncClassifier(string $imageFormat = ImageEncoder::JPEG, float $imageScale = ImageEncoder::DEFAULT_SCALE): SyncClassifier
    {
        return new SyncClassifier(
            $this->responsesClient(),
            $this->prompt(),
            new ImageEncoder($imageFormat, $imageScale),
            new MapResultParser(),
            $this->results,
            $this->config->rendersDir,
            $this->config->openAiModel,
        );
    }

    public function responsesClient(): ConcurrentResponsesClient
    {
        return new ConcurrentResponsesClient(
            // Retry on 429 and 5xx errors with exponential backoff, respecting Retry-After header
            new RetryableHttpClient($this->httpClient, maxRetries: 5),
            fn () => $this->config->openAiApiKey ?? throw new RuntimeException('OPENAI_API_KEY is not set'),
            $this->config->openAiBaseUrl,
        );
    }

    public function objectTypeClassifier(): ObjectTypeClassifier
    {
        return new ObjectTypeClassifier(
            $this->responsesClient(),
            new ObjectTypePrompt($this->config->openAiModel, $this->config->openAiReasoningEffort),
            $this->spriteImages,
            $this->objectTypes,
            $this->config->openAiModel,
        );
    }

    /**
     * @return list<ExportFormat<MapExport>>
     */
    public function mapExportFormats(): array
    {
        return [
            new JsonMapExportFormat($this->config->varDir . '/maps.json'),
            new HtmlMapExportFormat($this->config->rendersDir, $this->config->varDir . '/report.html'),
        ];
    }

    /**
     * @return list<ExportFormat<ObjectExport>>
     */
    public function objectExportFormats(): array
    {
        return [
            new JsonObjectExportFormat($this->config->objectsFile),
            new HtmlObjectExportFormat($this->config->spritesDir, $this->config->varDir . '/objects.html'),
        ];
    }

    public function objectExporter(): ObjectExporter
    {
        return new ObjectExporter($this->objectTypes);
    }

    public function prompt(): ClassificationPrompt
    {
        return new ClassificationPrompt($this->config->openAiModel, $this->config->openAiReasoningEffort);
    }

    public function application(): Application
    {
        $app = new Application('map-classifier', '0.1.0');

        $app->addCommand(new ClientDownloadCommand(new ClientDownloader($this->httpClient), $this->config->clientArchiveUrl, $this->config->downloadedClientDir));
        $app->addCommand(new KeysDownloadCommand(new MapKeyDownloader($this->httpClient), $this->config->keysDir, $this->config->keysArchiveUrl));
        $app->addCommand(new MapsRenderCommand($this->maps, $this->locations, new MapRenderService($this->renderer, $this->results, $this->config->rendersDir), $this->config->resultsDir));
        $app->addCommand(new ClassifyRunCommand($this->mapsToClassify, $this->locations, $this->syncClassifier(...), $this->config->openAiModel));
        $app->addCommand(new ObjectsAnalyzeCommand($this->maps, new MapObjectExtractor($this->keys), $this->locations, $this->results, $this->interactiveObjects, $this->objectProfiles));
        $app->addCommand(new ObjectsSpritesCommand(new SpriteExporter($this->config->dofusPath, $this->spriteImages), $this->objectProfiles, $this->config->spritesDir));
        $app->addCommand(new ObjectsClassifyCommand($this->objectProfiles, $this->spritesToClassify, $this->objectTypeClassifier(), $this->objectExporter(), $this->objectExportFormats(), $this->config->openAiModel));
        $app->addCommand(new ObjectsExportCommand($this->objectProfiles, $this->objectExporter(), $this->objectExportFormats()));
        $app->addCommand(new ExportCommand(new MapExporter($this->results), $this->locations, $this->mapExportFormats()));

        return $app;
    }
}
