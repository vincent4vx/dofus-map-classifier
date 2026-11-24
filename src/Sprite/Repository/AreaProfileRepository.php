<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Repository;

use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Sprite\Model\AreaProfile;
use RuntimeException;

use function basename;
use function glob;
use function is_dir;
use function sort;
use function unlink;
use function usort;

/**
 * Store the profile of each area in its own JSON file ({id}.json): a profile is as large as the sprites of its area
 *
 * JSON: see {@see AreaProfile}
 */
final readonly class AreaProfileRepository
{
    public function __construct(
        public string $directory,
    ) {}

    public function file(int $id): string
    {
        return $this->directory . '/' . $id . '.json';
    }

    /**
     * Replace the stored profiles: the profiles of areas no longer analyzed are removed
     *
     * @param iterable<AreaProfile> $profiles
     */
    public function saveAll(iterable $profiles): void
    {
        $saved = [];

        foreach ($profiles as $profile) {
            JsonFile::write($this->file($profile->id), $profile);
            $saved[basename($this->file($profile->id))] = true;
        }

        foreach ($this->files() as $file) {
            if (!isset($saved[basename($file)])) {
                unlink($file);
            }
        }
    }

    /**
     * @throws RuntimeException If the file does not exist or is invalid
     */
    public function get(int $id): AreaProfile
    {
        return AreaProfile::fromArray(JsonFile::read($this->file($id)));
    }

    /**
     * @return list<AreaProfile> Sorted by area id
     */
    public function all(): array
    {
        $profiles = [];

        foreach ($this->files() as $file) {
            $profiles[] = AreaProfile::fromArray(JsonFile::read($file));
        }

        usort($profiles, static fn (AreaProfile $a, AreaProfile $b) => $a->id <=> $b->id);

        return $profiles;
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $files = glob($this->directory . '/*.json') ?: [];
        sort($files);

        return $files;
    }
}
