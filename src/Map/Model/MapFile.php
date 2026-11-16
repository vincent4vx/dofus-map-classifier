<?php

declare(strict_types=1);

namespace MapClassifier\Map\Model;

/**
 * A map SWF file of the Dofus client
 */
final readonly class MapFile
{
    public function __construct(
        public int $id,
        public string $version,
        public bool $encrypted,
        public string $path,
    ) {}
}
