<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

use function array_map;
use function floatval;
use function is_array;

/**
 * Position of the cells of a sprite relative to the preferred path of their maps (see {@see \MapClassifier\Map\Model\PreferredPath})
 *
 * Only the maps with a preferred path are counted. A sprite drawing a road is mostly on the path (distance 0), a road border
 * or a decoration along the road next to it (distance 1). Compare with the cells of all the sprites: about 15% at distance 0,
 * 12% at 1, 8% at 2 and 65% farther.
 *
 * JSON: {"cells": int, "distances": {distance: frequency}}
 */
final readonly class PathProximity implements JsonSerializable
{
    public function __construct(
        /** Number of cells using the sprite on maps with a preferred path */
        public int $cells,
        /**
         * @var array<int, float> Frequency (0 to 1) of the cells at each distance from the path, in cells, indexed by distance (0 to 2).
         *     The remainder is the frequency of the farther cells
         */
        public array $distances,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['cells'] ?? 0),
            array_map(floatval(...), is_array($data['distances'] ?? null) ? $data['distances'] : []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['cells' => $this->cells, 'distances' => (object) $this->distances];
    }
}
