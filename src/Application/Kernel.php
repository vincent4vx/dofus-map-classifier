<?php

declare(strict_types=1);

namespace MapClassifier\Application;

use MapClassifier\Client\Command\ClientDownloadCommand;
use MapClassifier\Client\Service\ClientDownloader;
use MapClassifier\Infrastructure\Export\ExportFormat;
use MapClassifier\Infrastructure\OpenAI\ConcurrentResponsesClient;
use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Classification\Command\ClassifyRunCommand;
use MapClassifier\Map\Classification\Command\ExportCommand;
use MapClassifier\Map\Classification\Export\HtmlMapExportFormat;
use MapClassifier\Map\Classification\Export\JsonMapExportFormat;
use MapClassifier\Map\Classification\Model\MapExport;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Classification\Repository\ResultRenderErrorRepository;
use MapClassifier\Map\Classification\Service\ClassificationPrompt;
use MapClassifier\Map\Classification\Service\MapClassifier;
use MapClassifier\Map\Classification\Service\MapExporter;
use MapClassifier\Map\Classification\Service\MapResultParser;
use MapClassifier\Map\Classification\Service\MapsToClassify;
use MapClassifier\Map\Command\KeysDownloadCommand;
use MapClassifier\Map\Command\MapsRenderCommand;
use MapClassifier\Map\Repository\MapFileRepository;
use MapClassifier\Map\Repository\MapKeyRepository;
use MapClassifier\Map\Service\MapImageRenderer;
use MapClassifier\Map\Service\MapKeyDownloader;
use MapClassifier\Map\Service\MapRenderService;
use MapClassifier\Sprite\Classification\Command\SpritesClassifyCommand;
use MapClassifier\Sprite\Classification\Command\SpritesExportCommand;
use MapClassifier\Sprite\Classification\Export\HtmlSpriteExportFormat;
use MapClassifier\Sprite\Classification\Export\JsonSpriteExportFormat;
use MapClassifier\Sprite\Classification\Model\Export\SpriteExport;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Classification\Service\SpriteExporter;
use MapClassifier\Sprite\Classification\Service\SpritesToClassify;
use MapClassifier\Sprite\Classification\Service\SpriteTypeClassifier;
use MapClassifier\Sprite\Classification\Service\SpriteTypePrompt;
use MapClassifier\Sprite\Command\SpritesAnalyzeCommand;
use MapClassifier\Sprite\Command\SpritesImagesCommand;
use MapClassifier\Sprite\Repository\InteractiveObjectRepository;
use MapClassifier\Sprite\Repository\SpriteProfileRepository;
use MapClassifier\Sprite\Repository\SpriteRepository;
use MapClassifier\Sprite\Service\MapSpriteExtractor;
use MapClassifier\Sprite\Service\SpriteAnalysis;
use MapClassifier\Sprite\Service\SpriteImageExporter;
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

    public SpriteRepository $sprites {
        get => $this->sprites ??= new SpriteRepository($this->config->dofusPath, $this->config->spritesDir);
    }

    public SpriteProfileRepository $spriteProfiles {
        get => $this->spriteProfiles ??= new SpriteProfileRepository($this->config->spriteProfilesFile);
    }

    public SpritesToClassify $spritesToClassify {
        get => $this->spritesToClassify ??= new SpritesToClassify($this->spriteTypes, $this->sprites);
    }

    public SpriteTypeRepository $spriteTypes {
        get => $this->spriteTypes ??= new SpriteTypeRepository($this->config->spriteTypesDir);
    }

    public function __construct(
        public readonly Config $config,
        ?HttpClientInterface $httpClient = null,
    ) {
        if ($httpClient) {
            $this->httpClient = $httpClient;
        }
    }

    public function mapClassifier(): MapClassifier
    {
        return new MapClassifier(
            $this->responsesClient(),
            $this->prompt(),
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

    public function spriteTypeClassifier(): SpriteTypeClassifier
    {
        return new SpriteTypeClassifier(
            $this->responsesClient(),
            new SpriteTypePrompt($this->config->openAiModel, $this->config->openAiReasoningEffort),
            $this->sprites,
            $this->spriteTypes,
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
     * @return list<ExportFormat<SpriteExport>>
     */
    public function spriteExportFormats(): array
    {
        return [
            new JsonSpriteExportFormat($this->config->spritesFile),
            new HtmlSpriteExportFormat($this->config->spritesDir, $this->config->varDir . '/sprites.html'),
        ];
    }

    public function spriteExporter(): SpriteExporter
    {
        return new SpriteExporter($this->spriteTypes, $this->sprites);
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
        $app->addCommand(new MapsRenderCommand($this->maps, $this->locations, new MapRenderService($this->renderer, new ResultRenderErrorRepository($this->results), $this->config->rendersDir)));
        $app->addCommand(new ClassifyRunCommand($this->mapsToClassify, $this->locations, $this->mapClassifier(), $this->config->openAiModel));
        $app->addCommand(new SpritesAnalyzeCommand($this->maps, $this->locations, new SpriteAnalysis(new MapSpriteExtractor($this->keys), $this->locations, $this->results, $this->interactiveObjects, $this->sprites), $this->spriteProfiles));
        $app->addCommand(new SpritesImagesCommand(new SpriteImageExporter($this->sprites), $this->spriteProfiles, $this->config->spritesDir));
        $app->addCommand(new SpritesClassifyCommand($this->spriteProfiles, $this->spritesToClassify, $this->spriteTypeClassifier(), $this->spriteExporter(), $this->spriteExportFormats(), $this->config->openAiModel));
        $app->addCommand(new SpritesExportCommand($this->spriteProfiles, $this->spriteExporter(), $this->spriteExportFormats()));
        $app->addCommand(new ExportCommand(new MapExporter($this->results), $this->locations, $this->mapExportFormats()));

        return $app;
    }
}
