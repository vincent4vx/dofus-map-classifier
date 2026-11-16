<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\Export\ExportedSprite;
use MapClassifier\Sprite\Classification\Model\Export\SpriteExport;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Model\SpriteProfile;

/**
 * Join the sprites profiles with their type
 */
final readonly class SpriteExporter
{
    public function __construct(
        private SpriteTypeRepository $types,
    ) {}

    /**
     * @param array<string, array<int, SpriteProfile>> $profiles Profiles indexed by set value, then sprite id
     */
    public function export(array $profiles): SpriteExport
    {
        // New resolver on each export: it loads the stored types once
        $resolver = new SpriteTypeResolver($this->types);
        $sprites = [];

        foreach ($profiles as $set => $setProfiles) {
            $sprites[$set] = [];

            foreach ($setProfiles as $id => $profile) {
                $sprites[$set][$id] = new ExportedSprite($profile, $resolver->type($profile));
            }
        }

        return new SpriteExport($sprites);
    }
}
