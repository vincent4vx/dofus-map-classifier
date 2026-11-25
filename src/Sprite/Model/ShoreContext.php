<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

use function array_map;
use function floatval;
use function is_array;

/**
 * Position of the cells of an object relative to the water of their maps (see {@see \MapClassifier\Map\Model\WaterCells})
 *
 * A shore piece lies on water cells, and the land around them tells which edge of the water it draws: a cliff under the land on its
 * north west (land "nw"), a grass edge over the land on its south ("s")… The LLM types do not give this: some shore pieces look like
 * grass tufts. Open water has no land around ("").
 *
 * The native maps anchor some shore pieces on the land cells along the water instead: the cliffs around the lake of Astrub lie on its
 * last land cells, the water below them. The water around these land cells tells which edge they draw.
 *
 * JSON: {"cells": int, "water_share": float, "fills": {id: frequency}, "land": {directions: frequency}, "coast_share": float, "water": {directions: frequency}}
 */
final readonly class ShoreContext implements JsonSerializable
{
    public function __construct(
        /** Number of cells using the object on maps with water */
        public int $cells,
        /** Ratio (0 to 1) of these cells showing water */
        public float $waterShare,
        /** @var array<int, float> Frequency (0 to 1) of the water sprites under the object, on its water cells, indexed by ground id */
        public array $fills,
        /**
         * @var array<string, float> Frequency (0 to 1) of the land around its water cells, indexed by {@see \MapClassifier\Map\Model\Direction::key()}.
         *     The rare values are removed
         */
        public array $land,
        /** Ratio (0 to 1) of the cells on land with water around: along the water, on the land side */
        public float $coastShare = 0.0,
        /**
         * @var array<string, float> Frequency (0 to 1) of the water around these land cells, indexed by {@see \MapClassifier\Map\Model\Direction::key()}.
         *     The rare values are removed
         */
        public array $water = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['cells'] ?? 0),
            (float) ($data['water_share'] ?? 0.0),
            array_map(floatval(...), is_array($data['fills'] ?? null) ? $data['fills'] : []),
            array_map(floatval(...), is_array($data['land'] ?? null) ? $data['land'] : []),
            (float) ($data['coast_share'] ?? 0.0),
            array_map(floatval(...), is_array($data['water'] ?? null) ? $data['water'] : []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'cells' => $this->cells,
            'water_share' => $this->waterShare,
            'fills' => (object) $this->fills,
            'land' => (object) $this->land,
            'coast_share' => $this->coastShare,
            'water' => (object) $this->water,
        ];
    }
}
