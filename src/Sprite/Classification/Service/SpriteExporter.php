<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\Export\ExportedSprite;
use MapClassifier\Sprite\Classification\Model\Export\SpriteExport;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Model\SpriteProfiles;

/**
 * Join the sprites profiles with their type
 */
final readonly class SpriteExporter
{
    public function __construct(
        private SpriteTypeRepository $types,
    ) {}

    public function export(SpriteProfiles $profiles): SpriteExport
    {
        // New resolver on each export: it loads the stored types once
        $resolver = new SpriteTypeResolver($this->types);
        $sprites = [];

        foreach ($profiles as $profile) {
            $sprites[] = new ExportedSprite($profile, $resolver->type($profile));
        }

        return new SpriteExport($sprites);
    }
}
