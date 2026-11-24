<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Service;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Classification\Model\LocationType;
use MapClassifier\Map\Classification\Model\MapClassification;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Service\SubAreaBiomeResolver;
use MapClassifier\Map\Model\CellOffset;
use MapClassifier\Map\Model\PreferredPath;
use MapClassifier\Sprite\Model\AreaMaps;
use MapClassifier\Sprite\Model\AreaProfile;
use MapClassifier\Sprite\Model\AreaSpriteUsage;
use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\LocationUsage;
use MapClassifier\Sprite\Model\MapSprites;
use MapClassifier\Sprite\Model\Neighbor;
use MapClassifier\Sprite\Model\Orientation;
use MapClassifier\Sprite\Model\OrientationNeighbors;
use MapClassifier\Sprite\Model\OrientedSprite;
use MapClassifier\Sprite\Model\PathProximity;
use MapClassifier\Sprite\Model\RenderedSprite;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Model\UsageCount;
use MapClassifier\Sprite\Repository\SpriteRepository;
use MapClassifier\Sprite\Service\SpriteAnalyzer;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

final class SpriteAnalyzerTest extends TestCase
{
    use TempDirTrait;

    public function testProfiles(): void
    {
        $locations = [];
        $results = [];

        // 50 plain maps and 30 forest maps in sub area 1, 2 cave maps in sub area 2
        for ($id = 1; $id <= 82; ++$id) {
            [$subArea, $biome, $indoor] = match (true) {
                $id <= 50 => [1, 'plain', false],
                $id <= 80 => [1, 'forest', false],
                default => [2, 'cave', true],
            };

            $locations[$id] = new MapLocation($id, $subArea, 'Sub area ' . $subArea, 10, 'Area', 0, 'Continent');
            $results[$id] = $this->classified($id, $biome, $indoor);
        }

        // Not classified map: no biome, indoor or location type, but sub area is known
        $locations[100] = new MapLocation(100, 3, 'Sub area 3', 10, 'Area', 0, 'Continent');

        $analyzer = new SpriteAnalyzer($locations, $results, minRatio: 0.1);

        for ($id = 1; $id <= 82; ++$id) {
            // Sprite 345 is used 3 times per map on layer2, once on layer1 on forest maps
            $layers = ['layer2' => [345 => 3]];

            if ($id > 50 && $id <= 80) {
                $layers['layer1'] = [345 => 1];
            }

            // Ground 345 is a different sprite
            $layers['ground'] = [345 => 10];
            $analyzer->add($id, self::sprites($layers));
        }

        $analyzer->add(100, self::sprites(['layer1' => [7 => 2], 'background' => [12 => 1], 'ground' => [12 => 5]]));

        $profiles = $analyzer->profiles();

        $this->assertCount(4, $profiles);
        $this->assertSame([12, 345], array_keys($profiles->bySet(SpriteSet::Grounds)));
        $this->assertSame([7, 345], array_keys($profiles->bySet(SpriteSet::Objects)));

        $object = $profiles->get(SpriteSet::Objects, 345);

        $this->assertNotNull($object);
        $this->assertSame(SpriteSet::Objects, $object->set);
        $this->assertSame(Layer::Layer2, $object->layer);
        $this->assertSame(82 * 3 + 30, $object->occurrences);
        $this->assertSame(82, $object->maps);
        $this->assertSame(82, $object->classifiedMaps);

        // cave (2) is lower than 10% of plain (50): ignored
        // Uses: 3 per plain map, 4 per forest map (layer2 + layer1), 3 per cave map
        $this->assertEquals(['plain' => new UsageCount(50, 150), 'forest' => new UsageCount(30, 120)], $object->biomes);
        $this->assertEquals(['outdoor' => new UsageCount(80, 270)], $object->indoor);
        $this->assertEquals(['wild' => new UsageCount(82, 276)], $object->locationTypes);
        $this->assertEquals([new LocationUsage(1, 'Sub area 1', 80, 270)], $object->subAreas);
        $this->assertEquals([new LocationUsage(10, 'Area', 82, 276)], $object->areas);
        $this->assertSame(4.0, $object->biomes['forest']->perMap());

        $ground = $profiles->get(SpriteSet::Grounds, 345);

        $this->assertNotNull($ground);
        $this->assertSame(Layer::Ground, $ground->layer);
        $this->assertSame(820, $ground->occurrences);

        // Background and ground on the same map: counted once
        $background = $profiles->get(SpriteSet::Grounds, 12);
        $this->assertNotNull($background);
        $this->assertSame(Layer::Ground, $background->layer);
        $this->assertSame(1, $background->maps);
        $this->assertSame(0, $background->classifiedMaps);
        $this->assertSame([], $background->biomes);
        $this->assertEquals([new LocationUsage(3, 'Sub area 3', 1, 6)], $background->subAreas, 'Background and ground uses');

        $this->assertSame([
            'id' => 7,
            'layer' => 'layer1',
            'slope' => false,
            'occurrences' => 2,
            'maps' => 1,
            'classified_maps' => 0,
            'areas' => [['id' => 10, 'name' => 'Area', 'maps' => 1, 'occurrences' => 2]],
            'sub_areas' => [['id' => 3, 'name' => 'Sub area 3', 'maps' => 1, 'occurrences' => 2]],
            'biomes' => [],
            'indoor' => [],
            'location_types' => [],
            'movements' => [],
            'ground_share' => null,
            'bare_share' => null,
            'path' => null,
            'example' => null,
            'neighbors' => [],
            'interactive' => null,
        ], json_decode((string) json_encode($profiles->get(SpriteSet::Objects, 7)), true));
    }

    public function testSlope(): void
    {
        $sprites = new SpriteRepository('/not-used', $this->tempDir());

        foreach ([1 => 15, 2 => 15, 3 => 15, 4 => 1] as $id => $frames) {
            $sprites->save(SpriteSet::Grounds, $id, new RenderedSprite('png', 1.0, 1.0), $frames);
        }

        $sprites->save(SpriteSet::Objects, 1, new RenderedSprite('png', 1.0, 1.0), 15);

        $analyzer = new SpriteAnalyzer([], [], sprites: $sprites);
        // Ground 1: 4 sloped cells out of 6, ground 2: 1 out of 6, ground 3: never sloped, ground 4: sloped but without slope frames, ground 5: not exported
        $analyzer->add(1, self::sprites(['ground' => [1 => 4, 2 => 3, 3 => 2, 4 => 1, 5 => 1], 'background' => [3 => 1]], [1 => 3, 2 => 1, 4 => 1, 5 => 1]));
        $analyzer->add(2, self::sprites(['ground' => [1 => 2, 2 => 3], 'layer1' => [1 => 1]], [1 => 1]));

        $profiles = $analyzer->profiles();

        $this->assertTrue($profiles->get(SpriteSet::Grounds, 1)?->slope);
        $this->assertTrue($profiles->get(SpriteSet::Grounds, 2)?->slope, 'A single sloped cell is enough');
        $this->assertFalse($profiles->get(SpriteSet::Grounds, 3)?->slope, 'Never used on a slope');
        $this->assertFalse($profiles->get(SpriteSet::Grounds, 4)?->slope, 'No frame per slope value');
        $this->assertFalse($profiles->get(SpriteSet::Grounds, 5)?->slope, 'Not exported: frames unknown');
        $this->assertFalse($profiles->get(SpriteSet::Objects, 1)?->slope, 'Only ground sprites');

        $withoutRepository = new SpriteAnalyzer([], []);
        $withoutRepository->add(1, self::sprites(['ground' => [1 => 1]], [1 => 1]));
        $this->assertFalse($withoutRepository->profiles()->get(SpriteSet::Grounds, 1)?->slope);
    }

    public function testMovements(): void
    {
        $analyzer = new SpriteAnalyzer([], []);

        $map1 = new MapSprites();
        $map1->addCell(Layer::Ground, 1, movement: 4);
        $map1->addCell(Layer::Ground, 1, movement: 4);
        $map1->addCell(Layer::Ground, 1, movement: 0);
        $map1->addCell(Layer::Layer2, 1, movement: 0);
        $map1->addCell(Layer::Background, 2);
        $map1->addCell(Layer::Ground, 3);
        $analyzer->add(1, $map1);

        $map2 = new MapSprites();
        $map2->addCell(Layer::Ground, 1, movement: 7);
        $map2->addCell(Layer::Layer1, 1, movement: 4);
        $analyzer->add(2, $map2);

        $profiles = $analyzer->profiles();

        $this->assertSame([0 => 0.25, 4 => 0.5, 7 => 0.25], $profiles->get(SpriteSet::Grounds, 1)?->movements, 'Frequencies of the 4 ground cells, sorted by movement');
        $this->assertSame([0 => 0.5, 4 => 0.5], $profiles->get(SpriteSet::Objects, 1)?->movements, 'Both object layers');
        $this->assertSame([], $profiles->get(SpriteSet::Grounds, 2)?->movements, 'Background only: no cell');
        $this->assertSame(0.875, $profiles->get(SpriteSet::Grounds, 1)->groundShare, 'Average of 3 / 4 and 1 / 1 ground cells');
        $this->assertNull($profiles->get(SpriteSet::Objects, 1)->groundShare, 'Not a ground');
        $this->assertNull($profiles->get(SpriteSet::Grounds, 2)->groundShare, 'Background');
    }

    public function testPathProximity(): void
    {
        $analyzer = new SpriteAnalyzer([], []);

        $map1 = new MapSprites();
        $map1->addCell(Layer::Ground, 1, pathDistance: 0);
        $map1->addCell(Layer::Ground, 1, pathDistance: 0);
        $map1->addCell(Layer::Ground, 1, pathDistance: 1);
        $map1->addCell(Layer::Ground, 1, pathDistance: PreferredPath::FAR);
        $map1->addCell(Layer::Layer1, 2, pathDistance: 1);
        $analyzer->add(1, $map1);

        // No preferred path: not counted
        $map2 = new MapSprites();
        $map2->addCell(Layer::Ground, 1);
        $map2->addCell(Layer::Ground, 3);
        $analyzer->add(2, $map2);

        $profiles = $analyzer->profiles();

        $this->assertEquals(new PathProximity(4, [0 => 0.5, 1 => 0.25, 2 => 0.0]), $profiles->get(SpriteSet::Grounds, 1)?->path, 'Only the 4 cells of the map with a path');
        $this->assertEquals(new PathProximity(1, [0 => 0.0, 1 => 1.0, 2 => 0.0]), $profiles->get(SpriteSet::Objects, 2)?->path);
        $this->assertNull($profiles->get(SpriteSet::Grounds, 3)?->path, 'Never on a map with a path');
        $this->assertSame(['cells' => 4, 'distances' => [0 => 0.5, 1 => 0.25, 2 => 0]], json_decode((string) json_encode($profiles->get(SpriteSet::Grounds, 1)?->path), true));
    }

    public function testNeighbors(): void
    {
        $analyzer = new SpriteAnalyzer([], []);
        $wall = new OrientedSprite(1);
        $flipped = new Orientation(0, true);

        foreach ([1, 2] as $mapId) {
            $map = new MapSprites();

            foreach ([['3,-3' => $wall], ['-3,3' => $wall], []] as $neighbors) {
                $map->addCell(Layer::Layer2, 1);
                $map->addNeighbors(Layer::Layer2, 1, new Orientation(), $neighbors);
            }

            $map->addCell(Layer::Layer2, 1);
            $map->addNeighbors(Layer::Layer2, 1, $flipped, ['1,0' => new OrientedSprite(2)]);
            // Not the main layer: ignored
            $map->addCell(Layer::Layer1, 1);
            $map->addNeighbors(Layer::Layer1, 1, new Orientation(), ['0,1' => new OrientedSprite(3)]);
            $analyzer->add($mapId, $map);
        }

        $profile = $analyzer->profiles()->get(SpriteSet::Objects, 1);

        $this->assertNotNull($profile);
        $this->assertEquals([
            new OrientationNeighbors(new Orientation(), 6, [
                new Neighbor(new CellOffset(-3, 3), $wall, 2, 0.3333),
                new Neighbor(new CellOffset(3, -3), $wall, 2, 0.3333),
            ]),
            new OrientationNeighbors($flipped, 2, [new Neighbor(new CellOffset(1, 0), new OrientedSprite(2), 2, 1.0)]),
        ], $profile->neighbors, 'Main layer only, sorted by number of cells');
    }

    public function testBiomeFallbackOnSubArea(): void
    {
        $locations = [
            1 => new MapLocation(1, 5, 'Village'),
            2 => new MapLocation(2, 5, 'Village'),
            3 => new MapLocation(3, 5, 'Village'),
        ];
        $results = [
            1 => $this->classified(1, 'jungle', false),
            2 => $this->classified(2, null, true), // interior without biome
        ];

        $analyzer = new SpriteAnalyzer($locations, $results, new SubAreaBiomeResolver()->resolve($locations, $results));
        $analyzer->add(1, self::sprites(['layer1' => [1 => 1]]));
        $analyzer->add(2, self::sprites(['layer1' => [1 => 1]]));
        $analyzer->add(3, self::sprites(['layer1' => [1 => 1]])); // not classified

        $profile = $analyzer->profiles()->get(SpriteSet::Objects, 1);

        $this->assertNotNull($profile);

        $this->assertEquals(['jungle' => new UsageCount(3, 3)], $profile->biomes);
        $this->assertEquals(['outdoor' => new UsageCount(1, 1), 'indoor' => new UsageCount(1, 1)], $profile->indoor);
        $this->assertSame(2, $profile->classifiedMaps);
    }

    public function testInteractiveObject(): void
    {
        $wheat = new InteractiveObject(38, 'Wheat', 1);
        $analyzer = new SpriteAnalyzer([], [], interactiveObjects: [7511 => $wheat]);
        $analyzer->add(1, self::sprites(['layer2' => [7511 => 1, 12 => 1], 'ground' => [7511 => 1]]));

        $profiles = $analyzer->profiles();

        $this->assertSame($wheat, $profiles->get(SpriteSet::Objects, 7511)?->interactive);
        $this->assertNotNull($profiles->get(SpriteSet::Objects, 12));
        $this->assertNull($profiles->get(SpriteSet::Objects, 12)->interactive);
        $this->assertNull($profiles->get(SpriteSet::Grounds, 7511)?->interactive, 'Interactive objects only use the objects set');

        $array = json_decode((string) json_encode($profiles->get(SpriteSet::Objects, 7511)), true);
        $this->assertIsArray($array);
        $this->assertSame(['id' => 38, 'name' => 'Wheat', 'type' => 'resource'], $array['interactive']);
        $this->assertEquals($wheat, SpriteProfile::fromArray(SpriteSet::Objects, $array)->interactive);
        $this->assertNull(SpriteProfile::fromArray(SpriteSet::Objects, $profiles->get(SpriteSet::Objects, 12)->jsonSerialize())->interactive);
    }

    public function testFilter(): void
    {
        $analyzer = new SpriteAnalyzer([], [], minRatio: 0.1);

        $counts = static fn (array $maps) => array_map(static fn (int $count) => new UsageCount($count, $count * 2), $maps);

        $this->assertEquals($counts(['plain' => 50, 'forest' => 30, 'snow' => 5]), $analyzer->filter($counts(['snow' => 5, 'cave' => 2, 'plain' => 50, 'forest' => 30, 'sea' => 4])));
        $this->assertSame(['plain', 'forest', 'snow'], array_keys($analyzer->filter($counts(['snow' => 5, 'cave' => 2, 'plain' => 50, 'forest' => 30, 'sea' => 4]))), 'Sorted by maps');
        $this->assertSame([], $analyzer->filter([]));
        $this->assertEquals($counts(['a' => 1, 'b' => 1]), $analyzer->filter($counts(['a' => 1, 'b' => 1])));
    }

    public function testAreaProfiles(): void
    {
        // Area 19: 10 road maps (2 with a preferred path) and 4 village maps. Area 7: 30 maps
        $locations = $results = [];

        for ($id = 1; $id <= 44; ++$id) {
            $area = $id <= 14 ? 19 : 7;
            $locations[$id] = new MapLocation($id, $area * 10, 'Sub area', $area, $area === 19 ? 'Pandala' : 'Bonta');
            $results[$id] = $this->classified($id, $id <= 12 ? 'jungle' : 'plain', false, $id <= 10 ? 'road' : 'village');
        }

        $analyzer = new SpriteAnalyzer($locations, $results);

        for ($id = 1; $id <= 44; ++$id) {
            $map = new MapSprites();
            $map->addCell(Layer::Background, 253);
            $map->addActiveCell(true);
            $map->addActiveCell(false);
            $pathDistance = $id <= 2 ? 0 : null;
            $map->hasPreferredPath = $pathDistance !== null;

            // o991 draws the roads of area 19 only; o2250 paves its villages and every map of area 7
            if ($id <= 10) {
                $map->addCell(Layer::Layer1, 991, pathDistance: $pathDistance);
                $map->addCell(Layer::Layer1, 991, pathDistance: $pathDistance);
            } else {
                $map->addCell(Layer::Layer1, 2250);
            }

            $analyzer->add($id, $map);
        }

        $areas = $analyzer->areaProfiles();

        $this->assertSame([7, 19], array_map(static fn (AreaProfile $area) => $area->id, $areas));
        $pandala = $areas[1];
        $this->assertSame('Pandala', $pandala->name);
        $this->assertEquals(new AreaMaps(14, 14, 2, ['outdoor' => 14], ['jungle' => 12, 'plain' => 2], [
            'road' => ['maps' => 10, 'with_path' => 2],
            'village' => ['maps' => 4, 'with_path' => 0],
        ]), $pandala->maps);

        $cobbles = $pandala->sprite(SpriteSet::Objects, 991);
        $this->assertNotNull($cobbles);
        $this->assertSame(10, $cobbles->maps);
        $this->assertSame(20, $cobbles->occurrences);
        $this->assertEquals(['jungle' => new UsageCount(10, 20)], $cobbles->biomes);
        $this->assertEquals(new AreaSpriteUsage(10, 20, path: new PathProximity(4, [0 => 1.0, 1 => 0.0, 2 => 0.0])), $cobbles->on(LocationType::Road));
        $this->assertNull($cobbles->on(LocationType::Village));

        $slabs = $pandala->sprite(SpriteSet::Objects, 2250);
        $this->assertNotNull($slabs);
        $this->assertSame(4, $slabs->maps, 'The maps of area 7 are not counted');
        $this->assertSame(['village'], array_keys($slabs->byLocationType));
        $this->assertEquals(['plain' => new UsageCount(2, 2), 'jungle' => new UsageCount(2, 2)], $slabs->biomes, 'Complete distributions: no value ignored as noise');

        $background = $pandala->sprite(SpriteSet::Grounds, 253);
        $this->assertNotNull($background);
        $this->assertSame(0.5, $background->bareShare);
        $this->assertSame(0.5, $background->on(LocationType::Road)?->bareShare);

        $json = json_decode((string) json_encode($pandala), true);
        $this->assertSame(['id', 'name', 'maps', 'sprites'], array_keys($json));
        $this->assertEquals($pandala, AreaProfile::fromArray($json), 'Read back from its JSON');
    }

    private function classified(int $id, ?string $biome, bool $indoor, string $locationType = 'wild'): MapResult
    {
        return MapResult::success($id, 'v', MapClassification::fromArray([
            'biome' => $biome,
            'biome_other' => null,
            'indoor' => $indoor,
            'location_type' => $locationType,
            'tags' => [],
            'description' => '',
        ]), 'luna');
    }

    /**
     * @param array<string, array<int, int>> $cells Number of cells using each sprite, indexed by layer value then sprite id
     * @param array<int, int> $slopedGrounds Number of sloped ground cells, indexed by ground sprite id
     */
    private static function sprites(array $cells, array $slopedGrounds = []): MapSprites
    {
        $sprites = new MapSprites();

        foreach ($cells as $layer => $counts) {
            foreach ($counts as $id => $count) {
                for ($i = 0; $i < $count; ++$i) {
                    $sprites->addCell(Layer::from($layer), $id, $layer === 'ground' && $i < ($slopedGrounds[$id] ?? 0));
                }
            }
        }

        return $sprites;
    }
}
