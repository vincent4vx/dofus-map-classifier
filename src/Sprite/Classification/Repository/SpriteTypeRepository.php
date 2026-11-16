<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Repository;

use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Sprite\Classification\Model\SpriteTypeResult;
use MapClassifier\Sprite\Model\SpriteSet;

use function basename;
use function file_get_contents;
use function glob;
use function is_array;
use function is_file;
use function json_decode;
use function ksort;

/**
 * Store one JSON file per sprite: "{dir}/{set}/{id}.json"
 */
final readonly class SpriteTypeRepository
{
    public function __construct(
        private string $dir,
    ) {}

    public function get(SpriteSet $set, int $id): ?SpriteTypeResult
    {
        $file = $this->file($set, $id);

        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? SpriteTypeResult::fromArray($data) : null;
    }

    public function save(SpriteTypeResult $result): void
    {
        JsonFile::write($this->file($result->set, $result->id), $result);
    }

    /**
     * @return array<int, SpriteTypeResult> Indexed by sprite id, sorted
     */
    public function all(SpriteSet $set): array
    {
        $results = [];

        foreach (glob($this->dir . '/' . $set->value . '/*.json') ?: [] as $file) {
            $id = (int) basename($file, '.json');

            if ($result = $this->get($set, $id)) {
                $results[$id] = $result;
            }
        }

        ksort($results);

        return $results;
    }

    private function file(SpriteSet $set, int $id): string
    {
        return $this->dir . '/' . $set->value . '/' . $id . '.json';
    }
}
