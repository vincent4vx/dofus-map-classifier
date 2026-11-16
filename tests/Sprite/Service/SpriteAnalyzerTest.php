<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Service;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Classification\Model\MapClassification;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Service\SubAreaBiomeResolver;
use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\LocationUsage;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Service\SpriteAnalyzer;
use PHPUnit\Framework\TestCase;

final class SpriteAnalyzerTest extends TestCase
{
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
            $analyzer->add($id, $layers);
        }

        $analyzer->add(100, ['layer1' => [7 => 2], 'background' => [12 => 1], 'ground' => [12 => 5]]);

        $profiles = $analyzer->profiles();

        $this->assertSame(['grounds', 'objects'], array_keys($profiles));
        $this->assertSame([12, 345], array_keys($profiles['grounds']));
        $this->assertSame([7, 345], array_keys($profiles['objects']));

        $object = $profiles['objects'][345];
        $this->assertSame(SpriteSet::Objects, $object->set);
        $this->assertSame(Layer::Layer2, $object->layer);
        $this->assertSame(82 * 3 + 30, $object->occurrences);
        $this->assertSame(82, $object->maps);
        $this->assertSame(82, $object->classifiedMaps);

        // cave (2) is lower than 10% of plain (50): ignored
        $this->assertSame(['plain' => 50, 'forest' => 30], $object->biomes);
        $this->assertSame(['outdoor' => 80], $object->indoor);
        $this->assertSame(['wild' => 82], $object->locationTypes);
        $this->assertEquals([new LocationUsage(1, 'Sub area 1', 80)], $object->subAreas);
        $this->assertEquals([new LocationUsage(10, 'Area', 82)], $object->areas);

        $ground = $profiles['grounds'][345];
        $this->assertSame(Layer::Ground, $ground->layer);
        $this->assertSame(820, $ground->occurrences);

        // Background and ground on the same map: counted once
        $background = $profiles['grounds'][12];
        $this->assertSame(Layer::Ground, $background->layer);
        $this->assertSame(1, $background->maps);
        $this->assertSame(0, $background->classifiedMaps);
        $this->assertSame([], $background->biomes);
        $this->assertEquals([new LocationUsage(3, 'Sub area 3', 1)], $background->subAreas);

        $this->assertSame([
            'id' => 7,
            'layer' => 'layer1',
            'occurrences' => 2,
            'maps' => 1,
            'classified_maps' => 0,
            'areas' => [['id' => 10, 'name' => 'Area', 'maps' => 1]],
            'sub_areas' => [['id' => 3, 'name' => 'Sub area 3', 'maps' => 1]],
            'biomes' => [],
            'indoor' => [],
            'location_types' => [],
            'interactive' => null,
        ], json_decode((string) json_encode($profiles['objects'][7]), true));
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
        $analyzer->add(1, ['layer1' => [1 => 1]]);
        $analyzer->add(2, ['layer1' => [1 => 1]]);
        $analyzer->add(3, ['layer1' => [1 => 1]]); // not classified

        $profile = $analyzer->profiles()['objects'][1];

        $this->assertSame(['jungle' => 3], $profile->biomes);
        $this->assertSame(['outdoor' => 1, 'indoor' => 1], $profile->indoor);
        $this->assertSame(2, $profile->classifiedMaps);
    }

    public function testInteractiveObject(): void
    {
        $wheat = new InteractiveObject(38, 'Wheat', 1);
        $analyzer = new SpriteAnalyzer([], [], interactiveObjects: [7511 => $wheat]);
        $analyzer->add(1, ['layer2' => [7511 => 1, 12 => 1], 'ground' => [7511 => 1]]);

        $profiles = $analyzer->profiles();

        $this->assertSame($wheat, $profiles['objects'][7511]->interactive);
        $this->assertNull($profiles['objects'][12]->interactive);
        $this->assertNull($profiles['grounds'][7511]->interactive, 'Interactive objects only use the objects set');

        $array = json_decode((string) json_encode($profiles['objects'][7511]), true);
        $this->assertIsArray($array);
        $this->assertSame(['id' => 38, 'name' => 'Wheat', 'type' => 'resource'], $array['interactive']);
        $this->assertEquals($wheat, SpriteProfile::fromArray(SpriteSet::Objects, $array)->interactive);
        $this->assertNull(SpriteProfile::fromArray(SpriteSet::Objects, $profiles['objects'][12]->jsonSerialize())->interactive);
    }

    public function testFilter(): void
    {
        $analyzer = new SpriteAnalyzer([], [], minRatio: 0.1);

        $this->assertSame(['plain' => 50, 'forest' => 30, 'snow' => 5], $analyzer->filter(['snow' => 5, 'cave' => 2, 'plain' => 50, 'forest' => 30, 'sea' => 4]));
        $this->assertSame([], $analyzer->filter([]));
        $this->assertSame(['a' => 1, 'b' => 1], $analyzer->filter(['a' => 1, 'b' => 1]));
    }

    private function classified(int $id, ?string $biome, bool $indoor): MapResult
    {
        return MapResult::success($id, 'v', MapClassification::fromArray([
            'biome' => $biome,
            'biome_other' => null,
            'indoor' => $indoor,
            'location_type' => 'wild',
            'tags' => [],
            'description' => '',
        ]), 'luna');
    }
}
