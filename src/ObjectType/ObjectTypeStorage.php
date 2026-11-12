<?php

declare(strict_types=1);

namespace MapClassifier\ObjectType;

use MapClassifier\Objects\SpriteSet;
use MapClassifier\Storage\ResultStorage;
use RuntimeException;

use function basename;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function ksort;
use function mkdir;
use function rename;

/**
 * Store one JSON file per sprite: "{dir}/{set}/{id}.json"
 */
final readonly class ObjectTypeStorage
{
    public function __construct(
        private string $dir,
    ) {}

    public function get(SpriteSet $set, int $id): ?ObjectTypeResult
    {
        $file = $this->file($set, $id);

        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? ObjectTypeResult::fromArray($data) : null;
    }

    public function save(ObjectTypeResult $result): void
    {
        $dir = $this->dir . '/' . $result->set->value;

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory ' . $dir);
        }

        $file = $this->file($result->set, $result->id);
        file_put_contents($file . '.tmp', ResultStorage::encode($result->toArray()) . "\n");
        rename($file . '.tmp', $file);
    }

    /**
     * @return array<int, ObjectTypeResult> Indexed by sprite id, sorted
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
