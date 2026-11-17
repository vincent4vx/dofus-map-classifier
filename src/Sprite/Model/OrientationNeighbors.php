<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

use function array_filter;
use function array_map;
use function array_values;
use function is_array;

/**
 * Frequent neighbors of a sprite drawn with a given orientation, on the same layer, around its cell
 * Used to find the sprites composing larger objects (walls, fences, buildings, grounds made of several tiles…)
 *
 * JSON: {"rotation", "flip", "cells", "neighbors": [{@see Neighbor}]}
 */
final readonly class OrientationNeighbors implements JsonSerializable
{
    public function __construct(
        public Orientation $orientation,
        /** Number of cells using the sprite with this orientation */
        public int $cells,
        /** @var list<Neighbor> Sorted by count desc */
        public array $neighbors = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            new Orientation((int) ($data['rotation'] ?? 0), ($data['flip'] ?? false) === true),
            (int) ($data['cells'] ?? 0),
            is_array($data['neighbors'] ?? null) ? array_values(array_map(Neighbor::fromArray(...), array_filter($data['neighbors'], is_array(...)))) : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'rotation' => $this->orientation->rotation,
            'flip' => $this->orientation->flip,
            'cells' => $this->cells,
            'neighbors' => $this->neighbors,
        ];
    }
}
