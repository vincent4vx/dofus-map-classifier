<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\Export\ExportedSprite;
use MapClassifier\Sprite\Classification\Model\Export\ObjectExport;
use MapClassifier\Sprite\Classification\Repository\ObjectTypeRepository;
use MapClassifier\Sprite\Model\ObjectProfile;

/**
 * Join the sprites profiles with their type
 */
final readonly class ObjectExporter
{
    public function __construct(
        private ObjectTypeRepository $types,
    ) {}

    /**
     * @param array<string, array<int, ObjectProfile>> $profiles Profiles indexed by set value, then sprite id
     */
    public function export(array $profiles): ObjectExport
    {
        // New resolver on each export: it loads the stored types once
        $resolver = new ObjectTypeResolver($this->types);
        $sprites = [];

        foreach ($profiles as $set => $setProfiles) {
            $sprites[$set] = [];

            foreach ($setProfiles as $id => $profile) {
                $sprites[$set][$id] = new ExportedSprite($profile, $resolver->type($profile));
            }
        }

        return new ObjectExport($sprites);
    }
}
