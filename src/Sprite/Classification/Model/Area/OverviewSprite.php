<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model\Area;

use JsonSerializable;
use MapClassifier\Sprite\Classification\Model\SpriteType;
use MapClassifier\Sprite\Model\AreaSprite;
use MapClassifier\Sprite\Model\AreaSpriteUsage;
use Override;

use function round;

/**
 * A sprite in the overview of a location type of an area (see {@see LocationOverview}): its type, and how much the maps of this type use it
 *
 * JSON: {"set", "id", "layer", "name", "category", "transition", "maps", "share", "per_map", "ground_share", "bare_share", "path_lift"}
 */
final readonly class OverviewSprite implements JsonSerializable
{
    public function __construct(
        public AreaSprite $sprite,
        public AreaSpriteUsage $usage,
        /** Number of maps of the location type in the area */
        public int $typeMaps,
        /** Global classification of the sprite, null if not classified */
        public ?SpriteType $type,
    ) {}

    /**
     * Share (0 to 1) of the maps of the location type using the sprite
     */
    public function share(): float
    {
        return $this->typeMaps > 0 ? $this->usage->maps / $this->typeMaps : 0.0;
    }

    /**
     * How much more often than an average sprite its cells lie on the preferred path or touch it, on the maps of this type. Null without enough cells on such maps
     */
    public function pathLift(): ?float
    {
        return $this->usage->path !== null && $this->usage->path->cells >= LocationOverview::MIN_PATH_CELLS ? $this->usage->path->liftWithin(1) : null;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $lift = $this->pathLift();

        return [
            'set' => $this->sprite->set->value,
            'id' => $this->sprite->id,
            'layer' => $this->sprite->layer->value,
            'name' => $this->type?->name,
            'category' => $this->type?->category->value,
            'transition' => $this->type?->transition,
            'maps' => $this->usage->maps,
            'share' => round($this->share(), 4),
            'per_map' => round($this->usage->maps > 0 ? $this->usage->occurrences / $this->usage->maps : 0.0, 2),
            'ground_share' => $this->usage->groundShare,
            'bare_share' => $this->usage->bareShare,
            'path_lift' => $lift === null ? null : round($lift, 2),
        ];
    }
}
