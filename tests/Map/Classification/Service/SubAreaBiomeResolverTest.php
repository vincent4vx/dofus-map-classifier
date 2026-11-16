<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Classification\Service;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\ErrorCode;
use MapClassifier\Map\Classification\Model\MapClassification;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Service\SubAreaBiomeResolver;
use PHPUnit\Framework\TestCase;

final class SubAreaBiomeResolverTest extends TestCase
{
    public function testResolve(): void
    {
        $locations = [];
        $results = [];

        // Sub area 1: outdoor maps are preferred over indoor ones
        $this->map($locations, $results, 1, 1, 'forest', false);
        $this->map($locations, $results, 2, 1, 'forest', false);
        $this->map($locations, $results, 3, 1, 'plain', false);
        $this->map($locations, $results, 4, 1, 'cave', true);
        $this->map($locations, $results, 5, 1, 'cave', true);
        $this->map($locations, $results, 6, 1, 'cave', true);
        $this->map($locations, $results, 7, 1, null, true);
        $this->map($locations, $results, 8, 1, false, false); // not classified

        // Sub area 2: only interiors
        $this->map($locations, $results, 10, 2, 'underground', true);
        $this->map($locations, $results, 11, 2, null, true);

        // Sub area 3: nothing usable
        $this->map($locations, $results, 20, 3, null, true);
        $this->map($locations, $results, 21, 3, false, false);

        // Sub area 4: tie, use declaration order
        $this->map($locations, $results, 30, 4, 'swamp', false);
        $this->map($locations, $results, 31, 4, 'plain', false);

        $subAreas = (new SubAreaBiomeResolver())->resolve($locations, $results);

        $this->assertSame([1, 2, 3, 4], array_keys($subAreas));

        $this->assertSame(Biome::Forest, $subAreas[1]->biome);
        $this->assertSame(['forest' => 2, 'plain' => 1], $subAreas[1]->votes);
        $this->assertTrue($subAreas[1]->outdoorOnly);
        $this->assertSame(8, $subAreas[1]->totalMaps);
        $this->assertSame(7, $subAreas[1]->classifiedMaps);
        $this->assertSame('Area', $subAreas[1]->areaName);

        $this->assertSame(Biome::Underground, $subAreas[2]->biome);
        $this->assertFalse($subAreas[2]->outdoorOnly);

        $this->assertNull($subAreas[3]->biome);
        $this->assertSame([], $subAreas[3]->votes);
        $this->assertSame(1, $subAreas[3]->classifiedMaps);

        $this->assertSame(Biome::Plain, $subAreas[4]->biome);
        $this->assertSame(['plain' => 1, 'swamp' => 1], $subAreas[4]->votes);
    }

    /**
     * @param array<int, MapLocation> $locations
     * @param array<int, MapResult> $results
     * @param string|false|null $biome False for not classified map
     */
    private function map(array &$locations, array &$results, int $id, int $subArea, string|false|null $biome, bool $indoor): void
    {
        $locations[$id] = new MapLocation($id, $subArea, 'Sub area ' . $subArea, 10, 'Area');

        if ($biome === false) {
            if ($id % 2) {
                $results[$id] = MapResult::failure($id, 'v', ErrorCode::MissingKey);
            }

            return;
        }

        $results[$id] = MapResult::success($id, 'v', MapClassification::fromArray([
            'biome' => $biome,
            'biome_other' => null,
            'indoor' => $indoor,
            'location_type' => 'wild',
            'tags' => [],
            'description' => '',
        ]), 'luna');
    }
}
