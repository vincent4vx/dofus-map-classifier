<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Location\Repository;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Location\Model\NamedLocation;
use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function copy;
use function touch;

final class MapLocationRepositoryTest extends TestCase
{
    use TempDirTrait;

    private const string FIXTURES = __DIR__ . '/../../Fixtures/lang';

    public function testFind(): void
    {
        $repository = new MapLocationRepository(self::FIXTURES);

        $this->assertEquals(new MapLocation(
            mapId: 10000,
            subAreaId: 320,
            subAreaName: 'Kwismas Island',
            areaId: 42,
            areaName: 'Arctic Area',
            superAreaId: 0,
            superAreaName: 'Amaknean Continent',
        ), $repository->find(10000));

        $location = $repository->find(7411);
        $this->assertNotNull($location);
        $this->assertEquals(new NamedLocation(95, 'Astrub City'), $location->subArea());
        $this->assertEquals(new NamedLocation(18, 'Astrub'), $location->area());
        $this->assertEquals(new NamedLocation(0, 'Amaknean Continent'), $location->superArea());
        $this->assertNull(new MapLocation(1, 2)->area());
        $this->assertNull(new MapLocation(1, 2)->superArea());

        $this->assertNull($repository->find(999999));
        $this->assertCount(9271, $repository->all());
    }

    public function testNamePrefixIsRemoved(): void
    {
        $this->assertSame('Canidae Dungeon', (new MapLocationRepository(self::FIXTURES))->find(9650)?->subAreaName);
    }

    public function testUseLatestVersion(): void
    {
        copy(self::FIXTURES . '/maps_en_1251.swf', $this->tempDir() . '/maps_en_1251.swf');
        touch($this->tempDir() . '/maps_en_371.swf');
        touch($this->tempDir() . '/maps_fr_2000.swf');
        touch($this->tempDir() . '/maps_en_1300.swf.bak');

        $repository = new MapLocationRepository($this->tempDir(), 'en');

        $this->assertSame($this->tempDir() . '/maps_en_1251.swf', $repository->file());
        $this->assertSame(320, $repository->find(10000)?->subAreaId);
    }

    public function testMissingFile(): void
    {
        $repository = new MapLocationRepository($this->tempDir(), 'en');

        $errors = [];
        $this->assertSame([], $repository->tryAll(function (string $error) use (&$errors) { $errors[] = $error; }));
        $this->assertSame(['No maps_en_*.swf lang file found in ' . $this->tempDir()], $errors);

        $this->expectException(RuntimeException::class);
        $repository->all();
    }
}
