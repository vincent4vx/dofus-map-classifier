<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Repository;

use MapClassifier\Sprite\Model\RenderedSprite;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Model\SpriteSize;
use RuntimeException;

use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function ksort;
use function mkdir;
use function rename;

use const JSON_THROW_ON_ERROR;

/**
 * Exported sprites: "{dir}/{set}/{id}.png", with their visible size stored in "{dir}/{set}/sizes.json"
 */
final class SpriteImageRepository
{
    /** @var array<string, array<int, SpriteSize>> Sizes indexed by set, then id */
    private array $sizes = [];

    public function __construct(
        public readonly string $dir,
    ) {}

    public function path(SpriteSet $set, int $id): string
    {
        return $this->dir . '/' . $set->value . '/' . $id . '.png';
    }

    /**
     * PNG data of the exported sprite, or null if it is not exported
     */
    public function read(SpriteSet $set, int $id): ?string
    {
        $png = is_file($this->path($set, $id)) ? file_get_contents($this->path($set, $id)) : false;

        return $png === false ? null : $png;
    }

    public function exists(SpriteSet $set, int $id): bool
    {
        return is_file($this->path($set, $id)) && $this->size($set, $id) !== null;
    }

    /**
     * Visible size of the sprite, in game pixels
     */
    public function size(SpriteSet $set, int $id): ?SpriteSize
    {
        return $this->sizes($set)[$id] ?? null;
    }

    public function save(SpriteSet $set, int $id, RenderedSprite $sprite): void
    {
        $dir = $this->dir . '/' . $set->value;

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory ' . $dir);
        }

        $file = $this->path($set, $id);
        file_put_contents($file . '.tmp', $sprite->png);
        rename($file . '.tmp', $file);

        $this->sizes($set);
        $this->sizes[$set->value][$id] = new SpriteSize($sprite->width, $sprite->height);
    }

    /**
     * Write the sizes file of the set
     * Must be called after {@see save()} calls
     */
    public function flush(SpriteSet $set): void
    {
        $sizes = $this->sizes($set);
        ksort($sizes);

        $file = $this->dir . '/' . $set->value . '/sizes.json';
        file_put_contents($file . '.tmp', json_encode((object) $sizes, JSON_THROW_ON_ERROR));
        rename($file . '.tmp', $file);
    }

    /**
     * @return array<int, SpriteSize>
     */
    private function sizes(SpriteSet $set): array
    {
        if (isset($this->sizes[$set->value])) {
            return $this->sizes[$set->value];
        }

        $file = $this->dir . '/' . $set->value . '/sizes.json';
        $sizes = [];
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        foreach (is_array($data) ? $data : [] as $id => $size) {
            if ($size = SpriteSize::fromJson($size)) {
                $sizes[(int) $id] = $size;
            }
        }

        return $this->sizes[$set->value] = $sizes;
    }
}
