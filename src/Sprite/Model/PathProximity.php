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
    /** Frequency of the cells of all the sprites at each distance, on the maps with a preferred path: the reference to compare with */
    public const array BASELINE = [0 => 0.154, 1 => 0.123, 2 => 0.080];

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
     * How much more often than an average sprite the cells are on the path or within the given distance of it: 1 is the average
     */
    public function liftWithin(int $distance): float
    {
        $share = $reference = 0.0;

        for ($d = 0; $d <= $distance; ++$d) {
            $share += $this->distances[$d] ?? 0.0;
            $reference += self::BASELINE[$d] ?? 0.0;
        }

        return $reference > 0 ? $share / $reference : 0.0;
    }

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
