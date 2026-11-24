<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Service;

use MapClassifier\Map\Classification\Model\LocationType;
use MapClassifier\Sprite\Classification\Model\Area\AreaOverview;
use MapClassifier\Sprite\Classification\Model\Area\LocationOverview;
use MapClassifier\Sprite\Classification\Model\Area\OverviewSprite;
use MapClassifier\Sprite\Classification\Model\SpriteCategory;
use MapClassifier\Sprite\Model\AreaProfile;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\SpriteProfiles;

use function array_filter;
use function array_slice;
use function array_values;
use function in_array;
use function usort;

/**
 * Summarize the profile of an area, with the global types of its sprites, into its terrain and paths by location type
 */
final readonly class AreaOverviewBuilder
{
    /** Base ground tiles listed per location type, heaviest first */
    private const int MAX_GROUNDS = 8;

    public function __construct(
        private SpriteTypeResolver $types,
        /** Global profiles of the sprites: the interactive objects rename their type */
        private SpriteProfiles $profiles,
    ) {}

    public function build(AreaProfile $area): AreaOverview
    {
        $types = [];

        foreach ($area->maps->locationTypes as $value => $counts) {
            $type = LocationType::from($value);
            $sprites = [];

            foreach ($area->sprites as $byId) {
                foreach ($byId as $sprite) {
                    $usage = $sprite->on($type);

                    if ($usage === null) {
                        continue;
                    }

                    $profile = $this->profiles->get($sprite->set, $sprite->id);
                    $spriteType = $profile !== null ? $this->types->type($profile) : $this->types->classification($sprite->set, $sprite->id);
                    $candidate = new OverviewSprite($sprite, $usage, $counts['maps'], $spriteType);

                    if ($candidate->share() >= LocationOverview::MIN_SHARE) {
                        $sprites[] = $candidate;
                    }
                }
            }

            usort($sprites, static fn (OverviewSprite $a, OverviewSprite $b) => ($b->usage->maps <=> $a->usage->maps) ?: ($a->sprite->id <=> $b->sprite->id));

            $types[$value] = new LocationOverview(
                $counts['maps'],
                $counts['with_path'],
                array_values(array_filter($sprites, static fn (OverviewSprite $s) => $s->sprite->layer === Layer::Background)),
                array_slice(array_values(array_filter($sprites, self::isBaseGround(...))), 0, self::MAX_GROUNDS),
                array_values(array_filter($sprites, self::drawsPaths(...))),
            );
        }

        return new AreaOverview($area->id, $area->name, $area->maps, $types);
    }

    private static function isBaseGround(OverviewSprite $sprite): bool
    {
        return $sprite->sprite->layer === Layer::Ground
            && $sprite->type?->transition !== true
            && ($sprite->usage->groundShare ?? 0.0) >= LocationOverview::MIN_GROUND_SHARE;
    }

    private static function drawsPaths(OverviewSprite $sprite): bool
    {
        if (!in_array($sprite->sprite->layer, [Layer::Ground, Layer::Layer1], true)) {
            return false;
        }

        return $sprite->type?->category === SpriteCategory::Path || ($sprite->pathLift() ?? 0.0) >= LocationOverview::MIN_PATH_LIFT;
    }
}
