<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model\Area;

use JsonSerializable;
use Override;

/**
 * How the maps of one location type of an area are built: their terrain and their paths
 *
 * Only the sprites used on at least {@see MIN_SHARE} of the maps of the type are listed, by number of maps desc.
 *
 * JSON: {"maps": int, "with_path": int, "backgrounds": [{@see OverviewSprite}], "grounds": [{@see OverviewSprite}], "paths": [{@see OverviewSprite}]}
 */
final readonly class LocationOverview implements JsonSerializable
{
    /** A sprite used on less than this share of the maps of the type is ignored */
    public const float MIN_SHARE = 0.05;

    /** A ground is a base tile of the terrain when it covers at least this share of the ground cells of its maps */
    public const float MIN_GROUND_SHARE = 0.1;

    /** The position relative to the preferred path is judged with at least this number of cells on maps having one */
    public const int MIN_PATH_CELLS = 20;

    /** A sprite lying on the preferred path or touching it this much more often than an average sprite draws a path */
    public const float MIN_PATH_LIFT = 2.0;

    public function __construct(
        /** Classified maps of the type in the area */
        public int $maps,
        /** Those with a preferred path */
        public int $withPath,
        /**
         * @var list<OverviewSprite> Backgrounds of the maps. With a large bare_share, the background is the terrain itself;
         *     with a small one, it only fills the holes of the ground tiles
         */
        public array $backgrounds,
        /** @var list<OverviewSprite> Base ground tiles: grounds covering a large share of the ground cells of their maps, transitions excluded */
        public array $grounds,
        /**
         * @var list<OverviewSprite> Sprites drawing the paths, roads and streets, on the ground or on layer 1: classified as path,
         *     or lying on the preferred path of the maps (path_lift). A transition is a path border
         */
        public array $paths,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'maps' => $this->maps,
            'with_path' => $this->withPath,
            'backgrounds' => $this->backgrounds,
            'grounds' => $this->grounds,
            'paths' => $this->paths,
        ];
    }
}
