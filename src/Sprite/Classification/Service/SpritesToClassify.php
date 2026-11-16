<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteSelection;
use MapClassifier\Sprite\Model\SpriteSet;
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
     * @param array<string, array<int, SpriteProfile>> $profiles All profiles, indexed by set value then sprite id
     * @param SpriteSet|null $set Only sprites of this set
     * @param Layer|null $layer Only sprites mainly used on this layer
     * @param bool $interactiveOnly Only sprites of interactive objects
     * @param bool $force Also select already classified sprites
     * @param bool $retryErrors Also select sprites with a classification error
     *
     * @return array{sprites: list<SpriteProfile>, missingImages: int} Selected sprites, and number of sprites ignored because their image is not exported
     */
    public function select(
        array $profiles,
        SpriteSelection $selection,
        ?SpriteSet $set = null,
        ?Layer $layer = null,
        int $minMaps = 1,
        bool $interactiveOnly = false,
        bool $force = false,
        bool $retryErrors = false,
        ?int $limit = null,
    ): array {
        $sprites = [];
        $missingImages = 0;

        foreach (SpriteSet::cases() as $spriteSet) {
            if ($set !== null && $set !== $spriteSet) {
                continue;
            }

            foreach ($profiles[$spriteSet->value] ?? [] as $profile) {
                if (
                    ($layer !== null && $profile->layer !== $layer)
                    || ($interactiveOnly && $profile->interactive === null)
                    || $profile->maps < $minMaps
                    || !$selection->matches($profile->set, $profile->id)
                ) {
                    continue;
                }

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
        }

        usort($sprites, static fn (SpriteProfile $a, SpriteProfile $b) => $b->maps <=> $a->maps ?: $a->id <=> $b->id);

        return [
            'sprites' => $limit !== null ? array_slice($sprites, 0, $limit) : $sprites,
            'missingImages' => $missingImages,
        ];
    }
}
