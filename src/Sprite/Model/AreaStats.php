<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\MapClassification;

use function arsort;
use function ksort;

/**
 * Usage of the sprites on the maps of one area, accumulated map by map (see {@see \MapClassifier\Sprite\Service\SpriteAnalyzer})
 *
 * Each sprite gets the usage {@see SpriteStats} of the maps of the area, and of each location type: light ones, without neighbors.
 */
final class AreaStats
{
    private int $maps = 0;
    private int $classified = 0;
    private int $withPath = 0;

    /** @var array<string, int> */
    private array $indoor = [];

    /** @var array<string, int> */
    private array $biomes = [];

    /** @var array<string, array{maps: int, with_path: int}> */
    private array $locationTypes = [];

    /** @var array<string, array<int, SpriteStats>> Indexed by sprite set value, then sprite id */
    private array $sprites = [];

    /** @var array<string, array<string, array<int, SpriteStats>>> Indexed by location type value, then sprite set value, then sprite id */
    private array $byLocationType = [];

    public function __construct(
        public readonly int $id,
        public readonly ?string $name,
    ) {}

    /**
     * Count a map of the area and the sprites it uses. Must be called once per map
     *
     * @param Biome|null $biome Biome of the map, or of its sub area
     * @param MapClassification|null $classification Null if the map is not classified
     */
    public function addMap(MapSprites $sprites, ?MapLocation $location, ?Biome $biome, ?MapClassification $classification): void
    {
        ++$this->maps;
        $this->withPath += $sprites->hasPreferredPath ? 1 : 0;

        if ($biome !== null) {
            $this->biomes[$biome->value] = ($this->biomes[$biome->value] ?? 0) + 1;
        }

        $type = null;

        if ($classification !== null) {
            ++$this->classified;
            $indoor = $classification->indoor ? 'indoor' : 'outdoor';
            $this->indoor[$indoor] = ($this->indoor[$indoor] ?? 0) + 1;
            $type = $classification->locationType->value;
            $this->locationTypes[$type] ??= ['maps' => 0, 'with_path' => 0];
            ++$this->locationTypes[$type]['maps'];
            $this->locationTypes[$type]['with_path'] += $sprites->hasPreferredPath ? 1 : 0;
        }

        foreach ($sprites as $usage) {
            ($this->sprites[$usage->set->value][$usage->id] ??= new SpriteStats(detailed: false))
                ->addMap($usage, $location, $biome, $classification, null, $sprites->groundCells, $sprites->bareShare());

            if ($type !== null) {
                ($this->byLocationType[$type][$usage->set->value][$usage->id] ??= new SpriteStats(detailed: false))
                    ->addMap($usage, $location, $biome, $classification, null, $sprites->groundCells, $sprites->bareShare());
            }
        }
    }

    public function profile(): AreaProfile
    {
        arsort($this->biomes);
        arsort($this->indoor);
        ksort($this->locationTypes);
        $sprites = [];

        foreach ($this->sprites as $set => $stats) {
            ksort($stats);

            foreach ($stats as $id => $sprite) {
                $types = [];

                foreach ($this->byLocationType as $type => $byType) {
                    if (isset($byType[$set][$id])) {
                        $types[$type] = $byType[$set][$id];
                    }
                }

                $sprites[$set][$id] = AreaSprite::of(SpriteSet::from($set), $id, $sprite, $types);
            }
        }

        return new AreaProfile(
            $this->id,
            $this->name,
            new AreaMaps($this->maps, $this->classified, $this->withPath, $this->indoor, $this->biomes, $this->locationTypes),
            $sprites,
        );
    }
}
