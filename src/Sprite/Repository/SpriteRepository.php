<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Repository;

use Arakne\Swf\Extractor\DrawableInterface;
use Arakne\Swf\Extractor\SwfExtractor;
use Arakne\Swf\SwfFile;
use Closure;
use Generator;
use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Sprite\Model\RenderedSprite;
use MapClassifier\Sprite\Model\SpriteMetadata;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Model\SpriteSize;
use RuntimeException;

use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function is_numeric;
use function json_decode;
use function ksort;
use function mkdir;
use function rename;

/**
 * Sprites of the client, and their exported images
 *
 * - The sprites are read from the client: "clips/gfx/g*.swf" for grounds, "clips/gfx/o*.swf" for objects.
 *   A sprite exported by several files is read from the first one.
 * - The exported images are stored in "{dir}/{set}/{id}.png", with the metadata of the set in "{dir}/{set}/metadata.json"
 *   (see {@see SpriteMetadata})
 */
final class SpriteRepository
{
    private const string METADATA_FILE = 'metadata.json';

    /** @var array<string, array<int, SpriteMetadata>> Metadata indexed by set value, then sprite id */
    private array $metadata = [];

    public function __construct(
        private readonly string $dofusPath,
        /** Directory of the exported images */
        public readonly string $dir,
    ) {}

    /**
     * Iterate over the sprites of the client
     * The sprites are only loaded when calling the loader, and the SWF files are released after use
     *
     * @param array<int, mixed>|null $only Only these sprite ids (keys), or null for all sprites
     *
     * @return Generator<int, Closure(): DrawableInterface> Sprite loaders, indexed by sprite id
     */
    public function client(SpriteSet $set, ?array $only = null): Generator
    {
        $done = [];

        foreach (glob($this->dofusPath . '/clips/gfx/' . ($set === SpriteSet::Grounds ? 'g' : 'o') . '*.swf') ?: [] as $file) {
            $extractor = new SwfExtractor(new SwfFile($file));

            try {
                foreach ($extractor->exported() as $name => $_) {
                    if (!is_numeric($name)) {
                        continue;
                    }

                    $id = (int) $name;

                    if (isset($done[$id]) || ($only !== null && !isset($only[$id]))) {
                        continue;
                    }

                    $done[$id] = true;

                    yield $id => static fn () => $extractor->byName((string) $name);

                    $extractor->releaseIfOutOfMemory();
                }
            } finally {
                $extractor->release();
            }
        }
    }

    /**
     * Metadata of an exported sprite, or null if it is not exported
     */
    public function get(SpriteSet $set, int $id): ?SpriteMetadata
    {
        $metadata = $this->metadata($set)[$id] ?? null;

        return $metadata !== null && is_file($this->dir . '/' . $metadata->image) ? $metadata : null;
    }

    /**
     * PNG data of an exported sprite, or null if the file cannot be read
     */
    public function image(SpriteMetadata $metadata): ?string
    {
        $file = $this->dir . '/' . $metadata->image;
        $png = is_file($file) ? file_get_contents($file) : false;

        return $png === false ? null : $png;
    }

    /**
     * Save the image of a sprite
     * The metadata is only written by {@see flush()}
     *
     * @param int $frames Number of frames of the sprite in the client
     */
    public function save(SpriteSet $set, int $id, RenderedSprite $sprite, int $frames): SpriteMetadata
    {
        $dir = $this->dir . '/' . $set->value;

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory ' . $dir);
        }

        $metadata = new SpriteMetadata($set, $id, new SpriteSize($sprite->width, $sprite->height), $frames, $set->value . '/' . $id . '.png');
        $file = $this->dir . '/' . $metadata->image;
        file_put_contents($file . '.tmp', $sprite->png);
        rename($file . '.tmp', $file);

        $this->metadata($set);
        $this->metadata[$set->value][$id] = $metadata;

        return $metadata;
    }

    /**
     * Write the metadata file of the set
     * Must be called after {@see save()} calls
     */
    public function flush(SpriteSet $set): void
    {
        $metadata = $this->metadata($set);
        ksort($metadata);

        // Indexed by id (JSON object) even if ids are sequential
        JsonFile::write($this->dir . '/' . $set->value . '/' . self::METADATA_FILE, (object) $metadata);
    }

    /**
     * @return array<int, SpriteMetadata>
     */
    private function metadata(SpriteSet $set): array
    {
        if (isset($this->metadata[$set->value])) {
            return $this->metadata[$set->value];
        }

        $file = $this->dir . '/' . $set->value . '/' . self::METADATA_FILE;
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $metadata = [];

        foreach (is_array($data) ? $data : [] as $id => $sprite) {
            if (is_array($sprite) && ($sprite = SpriteMetadata::fromArray($set, (int) $id, $sprite))) {
                $metadata[(int) $id] = $sprite;
            }
        }

        return $this->metadata[$set->value] = $metadata;
    }
}
