<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Repository;

use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Model\SpriteSet;
use RuntimeException;

use function is_array;
use function is_file;

/**
 * Store the sprites profiles in a single JSON file (sprite_profiles.json), indexed by set then sprite id
 *
 * JSON: see {@see SpriteProfiles}
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
     * @throws RuntimeException If the file does not exist or is invalid
     */
    public function all(): SpriteProfiles
    {
        $data = JsonFile::read($this->file);
        $profiles = [];

        foreach (SpriteSet::cases() as $set) {
            foreach (is_array($data[$set->value] ?? null) ? $data[$set->value] : [] as $id => $profile) {
                if (is_array($profile)) {
                    $profiles[] = SpriteProfile::fromArray($set, ['id' => $id] + $profile);
                }
            }
        }

        return SpriteProfiles::of($profiles);
    }

    public function save(SpriteProfiles $profiles): void
    {
        JsonFile::write($this->file, $profiles);
    }
}
