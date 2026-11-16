<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Repository;

use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteSet;
use RuntimeException;

use function is_array;
use function is_file;

/**
 * Store the sprites profiles in a single JSON file (sprite_profiles.json), indexed by set then sprite id
 *
 * JSON: {"grounds": {id: {@see SpriteProfile}}, "objects": {id: {@see SpriteProfile}}}
 */
final readonly class SpriteProfileRepository
{
    public function __construct(
        public string $file,
    ) {}

    public function exists(): bool
    {
        return is_file($this->file);
    }

    /**
     * @return array<string, array<int, SpriteProfile>> Profiles indexed by set value, then sprite id
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
                    $profiles[$set->value][(int) $id] = SpriteProfile::fromArray($set, ['id' => $id] + $profile);
                }
            }
        }

        return $profiles;
    }

    /**
     * @param array<string, array<int, SpriteProfile>> $profiles Profiles indexed by set value, then sprite id
     */
    public function save(array $profiles): void
    {
        $data = [];

        foreach ($profiles as $set => $sprites) {
            $rows = [];

            foreach ($sprites as $id => $profile) {
                $rows[$id] = $profile;
            }

            // Keep sprites indexed by id (JSON object) even if ids are sequential
            $data[$set] = (object) $rows;
        }

        JsonFile::write($this->file, $data);
    }
}
