<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

/**
 * Use of a sprite chosen as example, to show the sprite in the context of a map (see {@see SpriteStats::example()})
 *
 * JSON: {"map", "cell"}
 */
final readonly class SpriteExample implements JsonSerializable
{
    public function __construct(
        public int $mapId,
        /** Cell using the sprite, on the main layer of the sprite */
        public int $cellId,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self((int) ($data['map'] ?? 0), (int) ($data['cell'] ?? 0));
    }

    /**
     * @return array<string, int>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['map' => $this->mapId, 'cell' => $this->cellId];
    }
}
