<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\SpriteCategory;
use MapClassifier\Sprite\Classification\Service\SpriteTypePrompt;
use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\LocationUsage;
use MapClassifier\Sprite\Model\SpriteMetadata;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Model\UsageCount;
use MapClassifier\Sprite\Model\SpriteSize;
use PHPUnit\Framework\TestCase;

use function array_column;

final class SpriteTypePromptTest extends TestCase
{
    public function testRequestBody(): void
    {
        $body = (new SpriteTypePrompt('luna', 'low'))->requestBody($this->profile(), self::metadata(53.0, 27.5), 'data:image/png;base64,xxx');

        $this->assertSame('luna', $body['model']);
        $this->assertSame(['effort' => 'low'], $body['reasoning']);
        $this->assertSame(SpriteTypePrompt::instructions(), $body['instructions']);
        $this->assertSame(['type' => 'input_image', 'image_url' => 'data:image/png;base64,xxx', 'detail' => 'high'], $body['input'][0]['content'][1]);
        $this->assertSame(SpriteTypePrompt::schema(), $body['text']['format']['schema']);
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
            TXT, SpriteTypePrompt::context($this->profile(), self::metadata(53.0, 27.5)));
    }

    public function testContextInteractiveObject(): void
    {
        $profile = new SpriteProfile(SpriteSet::Objects, 7511, Layer::Layer2, 10, 2, 0, [], [], [], [], [], new InteractiveObject(38, 'Wheat', 1));

        $this->assertStringContainsString(
            "\n" . 'Known interactive object (from the game data): "Wheat", resource harvested by a profession: tree, ore, cereal, plant, fish…. Use this name, and choose the category of what the sprite shows.',
            SpriteTypePrompt::context($profile, self::metadata(25.0, 13.0)),
        );
    }

    public function testContextSlope(): void
    {
        $profile = new SpriteProfile(SpriteSet::Grounds, 69, Layer::Ground, 7, 3, 0, [], [], [], [], [], slope: true);

        $this->assertStringContainsString("\nAlso used on sloped ground cells (relief).", SpriteTypePrompt::context($profile, self::metadata(53.0, 27.0, SpriteSet::Grounds, 15)));
    }

    public function testContextMinimalAndTeleportMarker(): void
    {
        $profile = new SpriteProfile(SpriteSet::Objects, 1030, Layer::Layer1, 10, 2, 0, [], [], [], [], []);

        $this->assertSame(<<<'TXT'
            Identify the sprite o1030.
            Visible size: 25 x 13 game pixels (one map cell is 53 x 27 pixels).
            Mainly used as: layer1 (element drawn flat on the ground, below characters: paths, carpets, shadows, small vegetation, ground details).
            Used 10 times, on 2 maps.
            Known sprite: this is the teleport cell marker (cell used to change map), category "marker".
            TXT, SpriteTypePrompt::context($profile, self::metadata(25.0, 13.0)));

        $ground = new SpriteProfile(SpriteSet::Grounds, 1030, Layer::Ground, 10, 2, 0, [], [], [], [], []);
        $this->assertStringNotContainsString('teleport', SpriteTypePrompt::context($ground, self::metadata(1.0, 1.0)));
        $this->assertStringStartsWith('Identify the sprite g1030.', SpriteTypePrompt::context($ground, self::metadata(1.0, 1.0)));
    }

    public function testSchemaAndInstructions(): void
    {
        $schema = SpriteTypePrompt::schema();

        $this->assertSame(array_column(SpriteCategory::cases(), 'value'), $schema['properties']['category']['enum']);
        $this->assertSame(array_keys($schema['properties']), $schema['required']);
        $this->assertFalse($schema['additionalProperties']);

        foreach (SpriteCategory::cases() as $category) {
            $this->assertStringContainsString('- ' . $category->value . ': ' . $category->description(), SpriteTypePrompt::instructions());
        }
    }

    private function profile(): SpriteProfile
    {
        return new SpriteProfile(
            set: SpriteSet::Objects,
            id: 345,
            layer: Layer::Layer2,
            occurrences: 120,
            maps: 80,
            classifiedMaps: 60,
            areas: [new LocationUsage(0, 'Amakna', 70), new LocationUsage(12, null, 10)],
            subAreas: [new LocationUsage(1, 'Village', 40)],
            biomes: ['plain' => new UsageCount(50, 150), 'forest' => new UsageCount(30, 40)],
            indoor: ['outdoor' => new UsageCount(60, 100)],
            locationTypes: ['wild' => new UsageCount(40, 70), 'road' => new UsageCount(20, 30)],
        );
    }

    private static function metadata(float $width, float $height, SpriteSet $set = SpriteSet::Objects, int $frames = 1): SpriteMetadata
    {
        return new SpriteMetadata($set, 1, new SpriteSize($width, $height), $frames, $set->value . '/1.png');
    }
}
