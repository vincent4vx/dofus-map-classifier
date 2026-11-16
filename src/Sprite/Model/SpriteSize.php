<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

use function is_array;
use function is_numeric;

/**
 * Visible size of a sprite, in game pixels (one map cell is 53 x 27 pixels)
 *
 * JSON (sprites/{set}/sizes.json): [width, height]
 */
final readonly class SpriteSize implements JsonSerializable
{
    public function __construct(
        public float $width,
        public float $height,
    ) {}

    /**
     * Parse the [width, height] JSON value
     */
    public static function fromJson(mixed $data): ?self
    {
        if (!is_array($data) || !is_numeric($data[0] ?? null) || !is_numeric($data[1] ?? null)) {
            return null;
        }

        return new self((float) $data[0], (float) $data[1]);
    }

    /**
     * @return array{float, float}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [$this->width, $this->height];
    }
}
