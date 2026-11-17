<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use function explode;

/**
 * A sprite drawn on a cell, with its orientation
 */
final readonly class OrientedSprite
{
    public function __construct(
        public int $id,
        public Orientation $orientation = new Orientation(),
    ) {}

    /**
     * Create from {@see key()}
     */
    public static function fromKey(string $key): self
    {
        [$id, $orientation] = explode(':', $key, 2) + [1 => '0'];

        return new self((int) $id, Orientation::fromKey($orientation));
    }

    /**
     * Unique key of the sprite and its orientation (e.g. "1231:0f")
     */
    public function key(): string
    {
        return $this->id . ':' . $this->orientation->key();
    }
}
