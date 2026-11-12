<?php

declare(strict_types=1);

namespace MapClassifier;

use MapClassifier\Batch\BatchFileBuilder;
use MapClassifier\Batch\BatchRegistry;
use MapClassifier\Batch\BatchResultParser;
use MapClassifier\Batch\BatchService;
use MapClassifier\Batch\ImageEncoder;
use MapClassifier\Classification\ClassificationPrompt;
use MapClassifier\Classification\MapsToClassify;
use MapClassifier\Classification\SyncClassifier;
use MapClassifier\Command\ClassifyImportCommand;
use MapClassifier\Command\ClassifyRunCommand;
use MapClassifier\Command\ClassifyStatusCommand;
use MapClassifier\Command\ClassifySubmitCommand;
use MapClassifier\Command\ExportCommand;
use MapClassifier\Command\KeysDownloadCommand;
use MapClassifier\Command\MapsRenderCommand;
use MapClassifier\Command\ReportCommand;
use MapClassifier\Lang\MapLocationRepository;
use MapClassifier\Map\MapFileRepository;
use MapClassifier\MapKey\MapKeyDownloader;
use MapClassifier\MapKey\MapKeyRepository;
use MapClassifier\OpenAI\BatchApiInterface;
use MapClassifier\OpenAI\OpenAIClient;
use MapClassifier\Render\MapImageRenderer;
use MapClassifier\Storage\ResultStorage;
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

    public ResultStorage $results {
        get => $this->results ??= new ResultStorage($this->config->resultsDir);
    }

    public BatchRegistry $batches {
        get => $this->batches ??= new BatchRegistry($this->config->batchesDir);
    }

    public MapsToClassify $mapsToClassify {
        get => $this->mapsToClassify ??= new MapsToClassify($this->maps, $this->results, $this->batches, $this->config->rendersDir);
    }

    public MapLocationRepository $locations {
        get => $this->locations ??= new MapLocationRepository($this->config->langDir, $this->config->lang);
    }

    public BatchApiInterface $batchApi {
        get => $this->batchApi ??= new OpenAIClient(
            $this->httpClient,
            $this->config->openAiApiKey ?? throw new RuntimeException('OPENAI_API_KEY is not set'),
            $this->config->openAiBaseUrl,
        );
    }

    public function __construct(
        public readonly Config $config,
        ?BatchApiInterface $batchApi = null,
        ?HttpClientInterface $httpClient = null,
    ) {
        if ($batchApi) {
            $this->batchApi = $batchApi;
        }

        if ($httpClient) {
            $this->httpClient = $httpClient;
        }
    }

    public function batchService(string $imageFormat = ImageEncoder::JPEG, float $imageScale = ImageEncoder::DEFAULT_SCALE, int $maxFileSize = 150 * 1024 * 1024): BatchService
    {
        return new BatchService(
            // Lazy: the API (and API key) is only required when actually calling it
            new LazyBatchApi(fn () => $this->batchApi),
            new BatchFileBuilder(
                $this->prompt(),
                new ImageEncoder($imageFormat, $imageScale),
                $maxFileSize,
            ),
            $this->batches,
            $this->results,
            new BatchResultParser(),
            $this->config->batchesDir,
            $this->config->rendersDir,
            $this->config->openAiModel,
        );
    }

    public function syncClassifier(string $imageFormat = ImageEncoder::JPEG, float $imageScale = ImageEncoder::DEFAULT_SCALE): SyncClassifier
    {
        return new SyncClassifier(
            // Retry on 429 and 5xx errors with exponential backoff, respecting Retry-After header
            new RetryableHttpClient($this->httpClient, maxRetries: 5),
            fn () => $this->config->openAiApiKey ?? throw new RuntimeException('OPENAI_API_KEY is not set'),
            $this->config->openAiBaseUrl,
            $this->prompt(),
            new ImageEncoder($imageFormat, $imageScale),
            new BatchResultParser(),
            $this->results,
            $this->config->rendersDir,
            $this->config->openAiModel,
        );
    }

    public function prompt(): ClassificationPrompt
    {
        return new ClassificationPrompt($this->config->openAiModel, $this->config->openAiReasoningEffort);
    }

    public function application(): Application
    {
        $app = new Application('map-classifier', '0.1.0');

        $app->addCommand(new KeysDownloadCommand(new MapKeyDownloader($this->httpClient), $this->config));
        $app->addCommand(new MapsRenderCommand($this->maps, $this->renderer, $this->locations, $this->results, $this->config));
        $app->addCommand(new ClassifySubmitCommand($this));
        $app->addCommand(new ClassifyRunCommand($this));
        $app->addCommand(new ClassifyStatusCommand($this));
        $app->addCommand(new ClassifyImportCommand($this));
        $app->addCommand(new ExportCommand($this->results, $this->locations, $this->config));
        $app->addCommand(new ReportCommand($this->results, $this->locations, $this->config));

        return $app;
    }
}
