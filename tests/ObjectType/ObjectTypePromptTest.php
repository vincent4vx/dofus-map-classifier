<?php

declare(strict_types=1);

namespace MapClassifier\Tests\ObjectType;

use MapClassifier\Lang\InteractiveObject;
use MapClassifier\Objects\Layer;
use MapClassifier\Objects\ObjectProfile;
use MapClassifier\Objects\SpriteSet;
use MapClassifier\ObjectType\ObjectCategory;
use MapClassifier\ObjectType\ObjectTypePrompt;
use PHPUnit\Framework\TestCase;

use function array_column;

final class ObjectTypePromptTest extends TestCase
{
    public function testRequestBody(): void
    {
        $body = (new ObjectTypePrompt('luna', 'low'))->requestBody($this->profile(), 'data:image/png;base64,xxx', ['width' => 53.0, 'height' => 27.5]);

        $this->assertSame('luna', $body['model']);
        $this->assertSame(['effort' => 'low'], $body['reasoning']);
        $this->assertSame(ObjectTypePrompt::instructions(), $body['instructions']);
        $this->assertSame(['type' => 'input_image', 'image_url' => 'data:image/png;base64,xxx', 'detail' => 'high'], $body['input'][0]['content'][1]);
        $this->assertSame(ObjectTypePrompt::schema(), $body['text']['format']['schema']);
        $this->assertTrue($body['text']['format']['strict']);
    }

    public function testContext(): void
    {
        $this->assertSame(<<<'TXT'
            Identify the sprite o345.
            Visible size: 53 x 27.5 game pixels (one map cell is 53 x 27 pixels).
            Mainly used as: layer2 (element standing on the ground, drawn above the floor: trees, walls, buildings, furniture, objects).
            Used 120 times, on 80 maps.
            Biomes of these maps (number of maps): plain (50), forest (30).
            Areas: Amakna (70), #12 (10).
            Sub areas: Village (40).
            Indoor / outdoor maps: outdoor (60).
            Kind of places: wild (40), road (20).
            TXT, ObjectTypePrompt::context($this->profile(), ['width' => 53.0, 'height' => 27.5]));
    }

    public function testContextInteractiveObject(): void
    {
        $profile = new ObjectProfile(SpriteSet::Objects, 7511, Layer::Layer2, 10, 2, 0, [], [], [], [], [], new InteractiveObject(38, 'Wheat', 1));

        $this->assertStringContainsString(
            "\n" . 'Known interactive object (from the game data): "Wheat", resource harvested by a profession: tree, ore, cereal, plant, fish…. Use this name, and choose the category of what the sprite shows.',
            ObjectTypePrompt::context($profile, ['width' => 25.0, 'height' => 13.0]),
        );
    }

    public function testContextMinimalAndTeleportMarker(): void
    {
        $profile = new ObjectProfile(SpriteSet::Objects, 1030, Layer::Layer1, 10, 2, 0, [], [], [], [], []);

        $this->assertSame(<<<'TXT'
            Identify the sprite o1030.
            Visible size: 25 x 13 game pixels (one map cell is 53 x 27 pixels).
            Mainly used as: layer1 (element drawn flat on the ground, below characters: paths, carpets, shadows, small vegetation, ground details).
            Used 10 times, on 2 maps.
            Known sprite: this is the teleport cell marker (cell used to change map), category "marker".
            TXT, ObjectTypePrompt::context($profile, ['width' => 25.0, 'height' => 13.0]));

        $ground = new ObjectProfile(SpriteSet::Grounds, 1030, Layer::Ground, 10, 2, 0, [], [], [], [], []);
        $this->assertStringNotContainsString('teleport', ObjectTypePrompt::context($ground, ['width' => 1.0, 'height' => 1.0]));
        $this->assertStringStartsWith('Identify the sprite g1030.', ObjectTypePrompt::context($ground, ['width' => 1.0, 'height' => 1.0]));
    }

    public function testSchemaAndInstructions(): void
    {
        $schema = ObjectTypePrompt::schema();

        $this->assertSame(array_column(ObjectCategory::cases(), 'value'), $schema['properties']['category']['enum']);
        $this->assertSame(array_keys($schema['properties']), $schema['required']);
        $this->assertFalse($schema['additionalProperties']);

        foreach (ObjectCategory::cases() as $category) {
            $this->assertStringContainsString('- ' . $category->value . ': ' . $category->description(), ObjectTypePrompt::instructions());
        }
    }

    private function profile(): ObjectProfile
    {
        return new ObjectProfile(
            set: SpriteSet::Objects,
            id: 345,
            layer: Layer::Layer2,
            occurrences: 120,
            maps: 80,
            classifiedMaps: 60,
            areas: [['id' => 0, 'name' => 'Amakna', 'maps' => 70], ['id' => 12, 'name' => null, 'maps' => 10]],
            subAreas: [['id' => 1, 'name' => 'Village', 'maps' => 40]],
            biomes: ['plain' => 50, 'forest' => 30],
            indoor: ['outdoor' => 60],
            locationTypes: ['wild' => 40, 'road' => 20],
        );
    }
}
