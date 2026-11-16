<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model;

use MapClassifier\Sprite\Model\SpriteProfile;

/**
 * Sprites selected for the type classification
 */
final readonly class SpritesToClassifySelection
{
    public function __construct(
        /** @var list<SpriteProfile> Sprites to classify, most used first */
        public array $sprites,
        /** Number of matching sprites ignored because their image is not exported */
        public int $missingImages,
    ) {}
}
