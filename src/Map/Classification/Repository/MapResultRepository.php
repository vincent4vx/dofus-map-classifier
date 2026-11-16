<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Repository;

use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Map\Classification\Model\MapResult;

use function basename;
use function file_get_contents;
use function glob;
use function is_array;
use function is_file;
use function json_decode;
use function ksort;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * Store one JSON file per map: "{dir}/{id}.json"
 */
final readonly class MapResultRepository
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
        JsonFile::write($this->file($result->id), $result->toArray());
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

    private function file(int $id): string
    {
        return $this->dir . '/' . $id . '.json';
    }
}
