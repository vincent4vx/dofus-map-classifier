<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Repository;

use InvalidArgumentException;
use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Sprite\Classification\Model\SpriteCategory;
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

        try {
            return is_array($data) ? SpriteTypeResult::fromArray($data) : null;
        } catch (InvalidArgumentException) {
            // Corrupted file: considered as not classified
            return null;
        }
    }

    public function save(SpriteTypeResult $result): void
    {
        JsonFile::write($this->file($result->set, $result->id), $result);
    }

    /**
     * Ground sprites (and backgrounds) classified as water
     *
     * @return array<int, true>
     */
    public function waterGrounds(): array
    {
        $grounds = [];

        foreach ($this->all(SpriteSet::Grounds) as $id => $result) {
            if ($result->classification?->category === SpriteCategory::Water) {
                $grounds[$id] = true;
            }
        }

        return $grounds;
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
