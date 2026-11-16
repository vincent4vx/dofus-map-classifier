<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

use function is_int;
use function is_numeric;
use function is_string;

/**
 * Metadata of an exported sprite (see {@see \MapClassifier\Sprite\Repository\SpriteRepository})
 *
 * JSON (sprites/{set}/metadata.json, indexed by sprite id): {"image", "width", "height", "frames"}
 */
final readonly class SpriteMetadata implements JsonSerializable
{
    /**
     * Number of frames of a ground sprite drawn on slopes: one frame per slope value (1 is flat, up to 15)
     */
    public const int SLOPE_FRAMES = 15;

    public function __construct(
        public SpriteSet $set,
        public int $id,
        /** Visible size of the sprite */
        public SpriteSize $size,
        /** Number of frames of the sprite in the client (1 for a sprite without timeline) */
        public int $frames,
        /** Path of the PNG image, relative to the sprites directory (e.g. "objects/345.png") */
        public string $image,
    ) {}

    /**
     * The sprite has one frame per slope value: it can be drawn on sloped cells
     */
    public function hasSlopeFrames(): bool
    {
        return $this->set === SpriteSet::Grounds && $this->frames === self::SLOPE_FRAMES;
    }

    /**
     * Create from the JSON of {@see jsonSerialize()}, or null if the data is invalid
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(SpriteSet $set, int $id, array $data): ?self
    {
        if (!is_string($data['image'] ?? null) || !is_numeric($data['width'] ?? null) || !is_numeric($data['height'] ?? null) || !is_int($data['frames'] ?? null)) {
            return null;
        }

        return new self($set, $id, new SpriteSize((float) $data['width'], (float) $data['height']), $data['frames'], $data['image']);
    }

    /**
     * @return array{image: string, width: float, height: float, frames: int}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'image' => $this->image,
            'width' => $this->size->width,
            'height' => $this->size->height,
            'frames' => $this->frames,
        ];
    }
}
