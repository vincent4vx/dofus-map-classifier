<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Repository;

use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Sprite\Model\ObjectProfile;
use MapClassifier\Sprite\Model\SpriteSet;
use RuntimeException;

use function is_array;
use function is_file;

/**
 * Store the sprites profiles in a single JSON file (objects.json), indexed by set then sprite id
 */
final readonly class ObjectProfileRepository
{
    public function __construct(
        public string $file,
    ) {}

    public function exists(): bool
    {
        return is_file($this->file);
    }

    /**
     * @return array<string, array<int, ObjectProfile>> Profiles indexed by set value, then sprite id
     *
     * @throws RuntimeException If the file does not exist or is invalid
     */
    public function all(): array
    {
        $data = JsonFile::read($this->file);
        $profiles = [];

        foreach (SpriteSet::cases() as $set) {
            $profiles[$set->value] = [];

            foreach (is_array($data[$set->value] ?? null) ? $data[$set->value] : [] as $id => $profile) {
                if (is_array($profile)) {
                    $profiles[$set->value][(int) $id] = ObjectProfile::fromArray($set, ['id' => $id] + $profile);
                }
            }
        }

        return $profiles;
    }

    /**
     * @param array<string, array<int, ObjectProfile>> $profiles Profiles indexed by set value, then sprite id
     * @param (callable(ObjectProfile): array<string, mixed>)|null $fields Additional fields to store on each profile
     */
    public function save(array $profiles, ?callable $fields = null): void
    {
        $data = [];

        foreach ($profiles as $set => $sprites) {
            $rows = [];

            foreach ($sprites as $id => $profile) {
                $rows[$id] = $profile->toArray() + ($fields ? $fields($profile) : []);
            }

            // Keep sprites indexed by id (JSON object) even if ids are sequential
            $data[$set] = (object) $rows;
        }

        JsonFile::write($this->file, $data);
    }
}
