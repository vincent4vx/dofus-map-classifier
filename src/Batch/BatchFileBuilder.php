<?php

declare(strict_types=1);

namespace MapClassifier\Batch;

use MapClassifier\Classification\ClassificationPrompt;
use MapClassifier\Map\MapFile;
use RuntimeException;

use function count;
use function fclose;
use function fopen;
use function fwrite;
use function is_dir;
use function json_encode;
use function mkdir;
use function sprintf;
use function strlen;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Build JSONL batch input files for the Responses API
 * Files are split to respect OpenAI limits (200 MB and 50 000 requests per file)
 */
final readonly class BatchFileBuilder
{
    public const string ENDPOINT = '/v1/responses';
    public const string CUSTOM_ID_PREFIX = 'map-';

    public function __construct(
        private ClassificationPrompt $prompt,
        private ImageEncoder $encoder,
        private int $maxFileSize = 150 * 1024 * 1024,
        private int $maxRequests = 50_000,
    ) {}

    /**
     * @param iterable<MapFile> $maps Maps to classify
     * @param callable(MapFile): string $renderPath Resolve the rendered PNG path of a map
     * @param string $prefix Prefix of generated files, without extension
     *
     * @return list<BatchInputFile>
     */
    public function build(iterable $maps, callable $renderPath, string $dir, string $prefix): array
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory ' . $dir);
        }

        $files = [];
        $handle = null;
        $path = '';
        $current = [];
        $size = 0;

        $close = static function () use (&$handle, &$path, &$current, &$size, &$files): void {
            if ($handle !== null) {
                fclose($handle);
                $files[] = new BatchInputFile($path, $current, $size);
                $handle = null;
                $current = [];
                $size = 0;
            }
        };

        foreach ($maps as $map) {
            $line = $this->line($map, $renderPath($map)) . "\n";
            $lineSize = strlen($line);

            if ($handle !== null && ($size + $lineSize > $this->maxFileSize || count($current) >= $this->maxRequests)) {
                $close();
            }

            if ($handle === null) {
                $path = sprintf('%s/%s-%03d.jsonl', $dir, $prefix, count($files) + 1);
                $handle = fopen($path, 'wb') ?: throw new RuntimeException('Cannot open ' . $path);
            }

            fwrite($handle, $line);
            $current[$map->id] = $map->version;
            $size += $lineSize;
        }

        $close();

        return $files;
    }

    private function line(MapFile $map, string $png): string
    {
        return json_encode([
            'custom_id' => self::CUSTOM_ID_PREFIX . $map->id,
            'method' => 'POST',
            'url' => self::ENDPOINT,
            'body' => $this->prompt->requestBody($map->id, $this->encoder->dataUrl($png)),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
