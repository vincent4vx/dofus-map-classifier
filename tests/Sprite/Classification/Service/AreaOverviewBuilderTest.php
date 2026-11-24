<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\Area\OverviewSprite;
use MapClassifier\Sprite\Classification\Model\SpriteType;
use MapClassifier\Sprite\Classification\Model\SpriteTypeResult;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Classification\Service\AreaOverviewBuilder;
use MapClassifier\Sprite\Classification\Service\SpriteTypeResolver;
use MapClassifier\Sprite\Model\AreaMaps;
use MapClassifier\Sprite\Model\AreaProfile;
use MapClassifier\Sprite\Model\AreaSprite;
use MapClassifier\Sprite\Model\AreaSpriteUsage;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\PathProximity;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

use function array_map;

final class AreaOverviewBuilderTest extends TestCase
{
    use TempDirTrait;

    public function testTerrainAndPathsByLocationType(): void
    {
        $types = new SpriteTypeRepository($this->tempDir());
        $classify = static fn (SpriteSet $set, int $id, string $category, string $name, bool $transition = false) => $types->save(SpriteTypeResult::success(
            $set,
            $id,
            SpriteType::fromArray(['category' => $category, 'name' => $name, 'keywords' => [], 'description' => '', 'transition' => $transition]),
            'luna',
        ));
        $classify(SpriteSet::Grounds, 253, 'ground', 'ochre dirt ground');
        $classify(SpriteSet::Grounds, 264, 'ground', 'ochre dirt tile');
        $classify(SpriteSet::Objects, 991, 'path', 'cobblestone patch');
        $classify(SpriteSet::Objects, 2250, 'path', 'pale stone paving');
        $classify(SpriteSet::Objects, 120, 'path', 'grassy dirt path', true);
        $classify(SpriteSet::Objects, 5, 'tree', 'bamboo');

        $sprite = static fn (SpriteSet $set, int $id, Layer $layer, array $byType) => new AreaSprite($set, $id, $layer, 0, 0, 0, byLocationType: $byType);
        $road = static fn (int $maps, ?float $ground = null, ?float $bare = null, ?PathProximity $path = null) => ['road' => new AreaSpriteUsage($maps, $maps * 3, $ground, $bare, $path)];
        $area = new AreaProfile(19, 'Neutral Pandala', new AreaMaps(40, 40, 10, locationTypes: ['road' => ['maps' => 40, 'with_path' => 10, 'floored' => 0]]), [
            'grounds' => [
                253 => $sprite(SpriteSet::Grounds, 253, Layer::Background, $road(30, bare: 0.8)),
                264 => $sprite(SpriteSet::Grounds, 264, Layer::Ground, $road(20, ground: 0.4)),
                // A ground covering few cells is a variant, not a base tile; on a single map, it is ignored
                300 => $sprite(SpriteSet::Grounds, 300, Layer::Ground, $road(20, ground: 0.02)),
                301 => $sprite(SpriteSet::Grounds, 301, Layer::Ground, $road(1, ground: 0.9)),
            ],
            'objects' => [
                991 => $sprite(SpriteSet::Objects, 991, Layer::Layer1, $road(34)),
                // Paving of the villages, on a single road map
                2250 => $sprite(SpriteSet::Objects, 2250, Layer::Layer1, $road(1)),
                120 => $sprite(SpriteSet::Objects, 120, Layer::Layer1, $road(12)),
                5 => $sprite(SpriteSet::Objects, 5, Layer::Layer2, $road(38)),
                // Not classified as a path, but lying along the preferred path of the maps
                700 => $sprite(SpriteSet::Objects, 700, Layer::Layer1, $road(8, path: new PathProximity(50, [0 => 0.4, 1 => 0.3, 2 => 0.1]))),
            ],
        ]);

        $overview = new AreaOverviewBuilder(new SpriteTypeResolver($types), SpriteProfiles::of([]))->build($area);
        $ids = static fn (array $sprites) => array_map(static fn (OverviewSprite $s) => $s->sprite->id, $sprites);
        $roads = $overview->locationTypes['road'];

        $this->assertSame(40, $roads->maps);
        $this->assertSame([253], $ids($roads->backgrounds));
        $this->assertSame([264], $ids($roads->grounds));
        $this->assertSame([991, 120, 700], $ids($roads->paths));
        $this->assertSame(0.85, $roads->paths[0]->share());

        $json = json_decode((string) json_encode($overview), true);
        $this->assertSame([
            'set' => 'objects', 'id' => 991, 'layer' => 'layer1', 'name' => 'cobblestone patch', 'category' => 'path', 'transition' => false,
            'maps' => 34, 'share' => 0.85, 'per_map' => 3, 'ground_share' => null, 'bare_share' => null, 'path_lift' => null,
        ], $json['location_types']['road']['paths'][0]);
        $this->assertSame(2.53, $json['location_types']['road']['paths'][2]['path_lift']);
        $this->assertTrue($json['location_types']['road']['paths'][1]['transition'], 'A path border');
    }
}
