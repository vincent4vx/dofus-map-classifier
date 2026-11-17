<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\Export\ExportedSprite;
use MapClassifier\Sprite\Classification\Model\Export\SpriteExport;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Repository\SpriteExampleRepository;
use MapClassifier\Sprite\Repository\SpriteRepository;

/**
 * Join the sprites profiles with their image metadata, their example map and their type
 */
final readonly class SpriteExporter
{
    public function __construct(
        private SpriteTypeRepository $types,
        private SpriteRepository $sprites,
        private ?SpriteExampleRepository $examples = null,
    ) {}

    public function export(SpriteProfiles $profiles): SpriteExport
    {
        // New resolver on each export: it loads the stored types once
        $resolver = new SpriteTypeResolver($this->types);
        $sprites = [];

        foreach ($profiles as $profile) {
            $sprites[] = new ExportedSprite(
                $profile,
                $this->sprites->get($profile->set, $profile->id),
                $resolver->type($profile),
                $this->examples?->exists($profile) ? $this->examples->path($profile) : null,
            );
        }

        return new SpriteExport($sprites);
    }
}
