<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model\Area;

use JsonSerializable;
use MapClassifier\Sprite\Model\AreaProfile;
use Override;

/**
 * Everything known about an area, in its own file (areas/{id}.json): its overview by location type, and the usage of its sprites
 *
 * JSON: {"id", "name", "maps": {@see \MapClassifier\Sprite\Model\AreaMaps}, "location_types": {type: {@see LocationOverview}},
 * "sprites": {"grounds": {id: {@see \MapClassifier\Sprite\Model\AreaSprite}}, "objects": {...}}}
 */
final readonly class AreaExport implements JsonSerializable
{
    public function __construct(
        public AreaOverview $overview,
        public AreaProfile $profile,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->overview->jsonSerialize() + ['sprites' => $this->profile->jsonSerialize()['sprites']];
    }
}
