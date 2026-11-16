<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Classification\Model;

use InvalidArgumentException;
use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\LocationType;
use MapClassifier\Map\Classification\Model\MapClassification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MapClassificationTest extends TestCase
{
    public function testFromArray(): void
    {
        $classification = MapClassification::fromArray([
            'biome' => 'snow',
            'biome_other' => 'ignored',
            'indoor' => false,
            'location_type' => 'wild',
            'tags' => [' ice ', 'pine trees'],
            'description' => ' A frozen lake. ',
        ]);

        $this->assertSame(Biome::Snow, $classification->biome);
        $this->assertNull($classification->biomeOther);
        $this->assertFalse($classification->indoor);
        $this->assertSame(LocationType::Wild, $classification->locationType);
        $this->assertSame(['ice', 'pine trees'], $classification->tags);
        $this->assertSame('A frozen lake.', $classification->description);
    }

    public function testOtherBiome(): void
    {
        $data = [
            'biome' => 'other',
            'biome_other' => 'building interior',
            'indoor' => true,
            'location_type' => 'house',
            'tags' => [],
            'description' => 'A house.',
        ];

        $classification = MapClassification::fromArray($data);

        $this->assertSame('building interior', $classification->biomeOther);
        $this->assertSame($data, $classification->toArray());
    }

    public function testNullBiome(): void
    {
        $classification = MapClassification::fromArray([
            'biome' => null,
            'biome_other' => 'building interior',
            'indoor' => true,
            'location_type' => 'house',
            'tags' => [],
            'description' => 'A house.',
        ]);

        $this->assertNull($classification->biome);
        $this->assertNull($classification->biomeOther);
        $this->assertNull($classification->toArray()['biome']);
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('provideInvalid')]
    public function testInvalid(array $override, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        MapClassification::fromArray($override + [
            'biome' => 'snow',
            'biome_other' => null,
            'indoor' => false,
            'location_type' => 'wild',
            'tags' => [],
            'description' => 'desc',
        ]);
    }

    /**
     * @return iterable<array{array<string, mixed>, string}>
     */
    public static function provideInvalid(): iterable
    {
        yield [['biome' => 'ocean'], 'Invalid biome: ocean'];
        yield [['biome' => 1], 'Invalid biome'];
        yield [['location_type' => 'castle'], 'Invalid location_type: castle'];
        yield [['indoor' => 'yes'], 'Invalid indoor'];
        yield [['tags' => 'tree'], 'Invalid tags'];
        yield [['tags' => [1]], 'Invalid tags'];
        yield [['description' => null], 'Invalid description'];
        yield [['biome_other' => 1], 'Invalid biome_other'];
    }
}
