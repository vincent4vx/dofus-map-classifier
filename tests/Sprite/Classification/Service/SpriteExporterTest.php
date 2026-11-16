<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\SpriteType;
use MapClassifier\Sprite\Classification\Model\SpriteTypeError;
use MapClassifier\Sprite\Classification\Model\SpriteTypeResult;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Classification\Service\SpriteExporter;
use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\RenderedSprite;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Model\UsageCount;
use MapClassifier\Sprite\Repository\SpriteRepository;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function json_decode;
use function json_encode;

final class SpriteExporterTest extends TestCase
{
    use TempDirTrait;

    public function testExport(): void
    {
        $types = new SpriteTypeRepository($this->tempDir());
        $types->save(SpriteTypeResult::success(SpriteSet::Objects, 7511, SpriteType::fromArray(['category' => 'crop', 'name' => 'wheat stalks', 'keywords' => ['cereal'], 'description' => 'Wheat.']), 'luna'));
        $types->save(SpriteTypeResult::failure(SpriteSet::Objects, 12, SpriteTypeError::ClassificationFailed, 'error'));

        $wheat = new SpriteProfile(SpriteSet::Objects, 7511, Layer::Layer2, 3, 1, 0, [], [], [], [], [], new InteractiveObject(38, 'Wheat', 1));
        $failed = new SpriteProfile(SpriteSet::Objects, 12, Layer::Layer2, 3, 1, 0, [], [], [], [], []);
        $ground = new SpriteProfile(SpriteSet::Grounds, 0, Layer::Ground, 5, 2, 1, [], [], ['plain' => new UsageCount(1, 5)], [], [], slope: true);
        $flatSlopeGround = new SpriteProfile(SpriteSet::Grounds, 1, Layer::Ground, 5, 2, 1, [], [], [], [], []);

        $sprites = new SpriteRepository('/not-used', $this->tempDir() . '/sprites');
        $sprites->save(SpriteSet::Grounds, 0, new RenderedSprite('png', 53.0, 27.0), 15);
        $sprites->save(SpriteSet::Grounds, 1, new RenderedSprite('png', 53.0, 27.0), 1);
        $sprites->save(SpriteSet::Objects, 7511, new RenderedSprite('png', 20.0, 30.0), 1);

        $export = new SpriteExporter($types, $sprites)->export(SpriteProfiles::of([$ground, $flatSlopeGround, $failed, $wheat]));

        $this->assertCount(4, $export);
        $this->assertSame(1, $export->classified());
        $exportedWheat = $export->get(SpriteSet::Objects, 7511);
        $this->assertNotNull($exportedWheat);
        $this->assertSame($wheat, $exportedWheat->profile);
        $this->assertSame('wheat', $exportedWheat->type?->name, 'Interactive object name is applied');
        $this->assertNull($export->get(SpriteSet::Objects, 12)?->type);
        $this->assertNotNull($export->get(SpriteSet::Grounds, 0));
        $this->assertNull($export->get(SpriteSet::Grounds, 0)->type);

        $json = (string) json_encode($export);
        $this->assertStringStartsWith('{"grounds":{"0":{"id":0,', $json, 'Sprites are indexed by id, even if sequential');

        $data = json_decode($json, true);
        $this->assertSame(['id', 'layer', 'slope', 'occurrences', 'maps', 'classified_maps', 'areas', 'sub_areas', 'biomes', 'indoor', 'location_types', 'interactive', 'image', 'type'], array_keys($data['objects']['7511']));
        $this->assertSame(['image' => 'objects/7511.png', 'width' => 20, 'height' => 30, 'frames' => 1], $data['objects']['7511']['image']);
        $this->assertNull($data['objects']['12']['image'], 'Not exported');
        $this->assertTrue($data['grounds']['0']['slope']);
        $this->assertSame(['category' => 'crop', 'name' => 'wheat', 'keywords' => ['cereal', 'wheat stalks', 'resource'], 'description' => 'Wheat.'], $data['objects']['7511']['type']);
        $this->assertNull($data['objects']['12']['type']);
    }
}
