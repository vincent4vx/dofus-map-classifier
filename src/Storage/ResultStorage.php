<?php

declare(strict_types=1);

namespace MapClassifier\Storage;

use RuntimeException;

use function basename;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function ksort;
use function mkdir;
use function rename;
use function unlink;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Store one JSON file per map: "{dir}/{id}.json"
 */
final readonly class ResultStorage
{
    public function __construct(
        private string $dir,
    ) {}

    public function get(int $id): ?MapResult
    {
        $file = $this->file($id);

        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        return is_array($data) ? MapResult::fromArray($data) : null;
    }

    public function save(MapResult $result): void
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0755, true) && !is_dir($this->dir)) {
            throw new RuntimeException('Cannot create directory ' . $this->dir);
        }

        $file = $this->file($result->id);
        $tmp = $file . '.tmp';

        file_put_contents($tmp, self::encode($result->toArray()) . "\n");
        rename($tmp, $file);
    }

    public function delete(int $id): void
    {
        @unlink($this->file($id));
    }

    /**
     * @return array<int, MapResult> Results indexed by map id, sorted by id
     */
    public function all(): array
    {
        $results = [];

        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            $id = (int) basename($file, '.json');

            if ($result = $this->get($id)) {
                $results[$id] = $result;
            }
        }

        ksort($results);

        return $results;
    }

    public static function encode(mixed $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function file(int $id): string
    {
        return $this->dir . '/' . $id . '.json';
    }
}
