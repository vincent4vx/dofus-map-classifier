<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

/**
 * Use of a sprite chosen as example, to show the sprite in the context of a map (see {@see SpriteStats::example()})
 *
 * JSON: {"map", "cell", "rotation", "flip"}
 */
final readonly class SpriteExample implements JsonSerializable
{
    public function __construct(
        public int $mapId,
        /** Cell using the sprite, on the main layer of the sprite */
        public int $cellId,
        /** Rotation and flip of the sprite on the cell */
        public Orientation $orientation = new Orientation(),
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['map'] ?? 0),
            (int) ($data['cell'] ?? 0),
            new Orientation((int) ($data['rotation'] ?? 0), ($data['flip'] ?? false) === true),
        );
    }

    /**
     * @return array<string, int|bool>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['map' => $this->mapId, 'cell' => $this->cellId, 'rotation' => $this->orientation->rotation, 'flip' => $this->orientation->flip];
    }
}
