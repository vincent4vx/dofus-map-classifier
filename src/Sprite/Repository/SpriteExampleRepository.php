<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Repository;

use GdImage;
use MapClassifier\Sprite\Model\SpriteProfile;
use RuntimeException;

use function dirname;
use function imagepng;
use function is_dir;
use function is_file;
use function mkdir;
use function rename;

/**
 * Store the example maps of the sprites (see {@see \MapClassifier\Sprite\Service\SpriteExampleRenderer}): "{dir}/{set}/{id}-{map}-{cell}.png"
 * The file name contains the example: a new example chosen by the analysis gives a new file
 */
final readonly class SpriteExampleRepository
{
    public function __construct(
        private string $dir,
    ) {}

    /**
     * Path of the example image, relative to the examples directory, or null if the sprite has no example
     */
    public function path(SpriteProfile $profile): ?string
    {
        if ($profile->example === null) {
            return null;
        }

        return $profile->set->value . '/' . $profile->id . '-' . $profile->example->mapId . '-' . $profile->example->cellId . '.png';
    }

    public function exists(SpriteProfile $profile): bool
    {
        return $this->file($profile) !== null;
    }

    /**
     * Path of the PNG file of the example, or null if not rendered
     */
    public function file(SpriteProfile $profile): ?string
    {
        $path = $this->path($profile);

        return $path !== null && is_file($file = $this->dir . '/' . $path) ? $file : null;
    }

    public function save(SpriteProfile $profile, GdImage $image): void
    {
        $file = $this->dir . '/' . ($this->path($profile) ?? throw new RuntimeException('The sprite ' . $profile->id . ' has no example'));
        $dir = dirname($file);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory ' . $dir);
        }

        // Write in a temporary file to never keep a partial image on crash
        if (!imagepng($image, $file . '.tmp') || !rename($file . '.tmp', $file)) {
            throw new RuntimeException('Cannot write image ' . $file);
        }
    }
}
