<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use MapClassifier\Map\Model\RenderError;

/**
 * Result of the analysis of the sprites used by the maps
 */
final readonly class SpriteAnalysisReport
{
    public function __construct(
        public SpriteProfiles $profiles,
        /** Number of analyzed maps */
        public int $analyzedMaps,
        /** @var array<string, int> Number of maps which cannot be loaded, indexed by {@see RenderError} value */
        public array $skippedMaps = [],
    ) {}
}
