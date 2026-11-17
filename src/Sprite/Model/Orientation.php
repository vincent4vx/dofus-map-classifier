<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use Arakne\MapParser\Parser\LayerObjectInterface;

use function str_ends_with;

/**
 * Orientation of a sprite drawn on a cell: rotation (only on grounds and layer1) and horizontal flip
 */
final readonly class Orientation
{
    public function __construct(
        /** Rotation, by quarter of turn (0 to 3) */
        public int $rotation = 0,
        public bool $flip = false,
    ) {}

    public static function of(LayerObjectInterface $object): self
    {
        return new self($object->rotation, $object->flip);
    }

    /**
     * Create from {@see key()}
     */
    public static function fromKey(string $key): self
    {
        return new self((int) $key, str_ends_with($key, 'f'));
    }

    /**
     * Unique key of the orientation: the rotation, followed by "f" if flipped (e.g. "0", "1f")
     */
    public function key(): string
    {
        return $this->rotation . ($this->flip ? 'f' : '');
    }
}
