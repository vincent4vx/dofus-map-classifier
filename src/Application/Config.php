<?php

declare(strict_types=1);

namespace MapClassifier\Application;

use function getenv;
use function rtrim;

/**
 * Application configuration, loaded from environment variables.
 */
final class Config
{
    public const string DEFAULT_KEYS_ARCHIVE_URL = 'https://codeload.github.com/Daweyy/MapKeys-DR/tar.gz/refs/heads/main';

    public function __construct(
        /** Path of the Dofus 1.29 client */
        public readonly string $dofusPath,
        /** Directory used to store all generated data (keys, renders, results, batches, cache) */
        public readonly string $varDir,
        public readonly ?string $openAiApiKey = null,
        public readonly string $openAiModel = 'luna',
        /** Reasoning effort sent to the model (only for reasoning models). Null to omit. */
        public readonly ?string $openAiReasoningEffort = null,
        public readonly string $openAiBaseUrl = 'https://api.openai.com/v1',
        public readonly string $keysArchiveUrl = self::DEFAULT_KEYS_ARCHIVE_URL,
        /** Directory of the lang swf files (e.g. maps_en_1251.swf) */
        public readonly string $langDir = '/lang',
        /** Lang to use for names */
        public readonly string $lang = 'en',
    ) {}

    public string $keysDir { get => $this->varDir . '/keys'; }
    public string $rendersDir { get => $this->varDir . '/renders'; }
    public string $resultsDir { get => $this->varDir . '/results'; }
    public string $batchesDir { get => $this->varDir . '/batches'; }
    public string $cacheDir { get => $this->varDir . '/cache'; }
    public string $mapsDir { get => $this->dofusPath . '/data/maps'; }
    public string $spritesDir { get => $this->varDir . '/sprites'; }
    public string $objectTypesDir { get => $this->varDir . '/object_types'; }
    public string $objectsFile { get => $this->varDir . '/objects.json'; }

    public static function fromEnv(string $projectDir): self
    {
        $env = static fn (string $name): ?string => ($value = getenv($name)) === false || $value === '' ? null : $value;

        return new self(
            dofusPath: rtrim($env('DOFUS_PATH') ?? '/dofus', '/'),
            varDir: rtrim($env('VAR_DIR') ?? $projectDir . '/var', '/'),
            openAiApiKey: $env('OPENAI_API_KEY'),
            openAiModel: $env('OPENAI_MODEL') ?? 'luna',
            openAiReasoningEffort: $env('OPENAI_REASONING_EFFORT'),
            openAiBaseUrl: rtrim($env('OPENAI_BASE_URL') ?? 'https://api.openai.com/v1', '/'),
            keysArchiveUrl: $env('MAP_KEYS_ARCHIVE_URL') ?? self::DEFAULT_KEYS_ARCHIVE_URL,
            langDir: rtrim($env('LANG_DIR') ?? '/lang', '/'),
            lang: $env('DOFUS_LANG') ?? 'en',
        );
    }
}
