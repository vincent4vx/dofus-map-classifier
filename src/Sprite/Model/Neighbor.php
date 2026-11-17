<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use MapClassifier\Map\Model\CellOffset;
use Override;

/**
 * Sprite frequently found near a sprite, on the same layer, at a given offset (see {@see OrientationNeighbors})
 *
 * JSON: {"x", "y", "id", "rotation", "flip", "count", "frequency"}
 */
final readonly class Neighbor implements JsonSerializable
{
    public function __construct(
        /** Position of the neighbor from the sprite cell */
        public CellOffset $offset,
        public OrientedSprite $sprite,
        /** Number of cells of the sprite having this neighbor at this offset */
        public int $count,
        /** Ratio (0 to 1) of the cells of the sprite having this neighbor at this offset */
        public float $frequency,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            new CellOffset((int) ($data['x'] ?? 0), (int) ($data['y'] ?? 0)),
            new OrientedSprite((int) ($data['id'] ?? 0), new Orientation((int) ($data['rotation'] ?? 0), ($data['flip'] ?? false) === true)),
            (int) ($data['count'] ?? 0),
            (float) ($data['frequency'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'x' => $this->offset->x,
            'y' => $this->offset->y,
            'id' => $this->sprite->id,
            'rotation' => $this->sprite->orientation->rotation,
            'flip' => $this->sprite->orientation->flip,
            'count' => $this->count,
            'frequency' => $this->frequency,
        ];
    }
}
