<?php

declare(strict_types=1);

namespace MapClassifier\Map\Model;

use Arakne\MapParser\Parser\LayerObjectInterface;

/**
 * An empty object layer, to render a cell without its object
 */
final readonly class NoLayerObject implements LayerObjectInterface
{
    public function __construct(
        public int $number = 0,
        /** @var int<0, 3> */
        public int $rotation = 0,
        public bool $flip = false,
        public bool $active = false,
    ) {}
}
