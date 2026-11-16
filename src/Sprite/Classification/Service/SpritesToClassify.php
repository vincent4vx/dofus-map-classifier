<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\SpritesToClassifySelection;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Model\SpriteFilter;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Repository\SpriteImageRepository;

use function array_slice;
use function usort;

/**
 * Select the sprites to classify, most used first
 */
final readonly class SpritesToClassify
{
    public function __construct(
        private SpriteTypeRepository $types,
        private SpriteImageRepository $images,
    ) {}

    /**
     * @param bool $force Also select already classified sprites
     * @param bool $retryErrors Also select sprites with a classification error
     * @param int|null $limit Maximum number of sprites to select
     */
    public function select(SpriteProfiles $profiles, SpriteFilter $filter, bool $force = false, bool $retryErrors = false, ?int $limit = null): SpritesToClassifySelection
    {
        $sprites = [];
        $missingImages = 0;

        foreach ($filter->filter($profiles) as $profile) {
            $result = $this->types->get($profile->set, $profile->id);

            if ($result !== null && !$force && ($result->ok || !$retryErrors)) {
                continue;
            }

            if (!$this->images->exists($profile->set, $profile->id)) {
                ++$missingImages;
                continue;
            }

            $sprites[] = $profile;
        }

        usort($sprites, static fn (SpriteProfile $a, SpriteProfile $b) => $b->maps <=> $a->maps ?: $a->id <=> $b->id);

        return new SpritesToClassifySelection($limit !== null ? array_slice($sprites, 0, $limit) : $sprites, $missingImages);
    }
}
