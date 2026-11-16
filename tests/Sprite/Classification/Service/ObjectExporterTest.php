<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\ObjectTypeClassification;
use MapClassifier\Sprite\Classification\Model\ObjectTypeResult;
use MapClassifier\Sprite\Classification\Repository\ObjectTypeRepository;
use MapClassifier\Sprite\Classification\Service\ObjectExporter;
use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\ObjectProfile;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function json_decode;
use function json_encode;

final class ObjectExporterTest extends TestCase
{
    use TempDirTrait;

    public function testExport(): void
    {
        $types = new ObjectTypeRepository($this->tempDir());
        $types->save(ObjectTypeResult::success(SpriteSet::Objects, 7511, ObjectTypeClassification::fromArray(['category' => 'crop', 'name' => 'wheat stalks', 'keywords' => ['cereal'], 'description' => 'Wheat.']), 'luna'));
        $types->save(ObjectTypeResult::failure(SpriteSet::Objects, 12, 'error'));

        $wheat = new ObjectProfile(SpriteSet::Objects, 7511, Layer::Layer2, 3, 1, 0, [], [], [], [], [], new InteractiveObject(38, 'Wheat', 1));
        $failed = new ObjectProfile(SpriteSet::Objects, 12, Layer::Layer2, 3, 1, 0, [], [], [], [], []);
        $ground = new ObjectProfile(SpriteSet::Grounds, 0, Layer::Ground, 5, 2, 1, [], [], ['plain' => 1], [], []);

        $export = new ObjectExporter($types)->export(['grounds' => [0 => $ground], 'objects' => [12 => $failed, 7511 => $wheat]]);

        $this->assertSame(3, $export->count());
        $this->assertSame(1, $export->classified());
        $this->assertSame($wheat, $export->sprites['objects'][7511]->profile);
        $this->assertSame('wheat', $export->sprites['objects'][7511]->type?->name, 'Interactive object name is applied');
        $this->assertNull($export->sprites['objects'][12]->type);
        $this->assertNull($export->sprites['grounds'][0]->type);

        $json = (string) json_encode($export);
        $this->assertStringStartsWith('{"grounds":{"0":{"id":0,', $json, 'Sprites are indexed by id, even if sequential');

        $data = json_decode($json, true);
        $this->assertSame(['id', 'layer', 'occurrences', 'maps', 'classified_maps', 'areas', 'sub_areas', 'biomes', 'indoor', 'location_types', 'interactive', 'type'], array_keys($data['objects']['7511']));
        $this->assertSame(['category' => 'crop', 'name' => 'wheat', 'keywords' => ['cereal', 'wheat stalks', 'resource'], 'description' => 'Wheat.'], $data['objects']['7511']['type']);
        $this->assertNull($data['objects']['12']['type']);
    }
}
