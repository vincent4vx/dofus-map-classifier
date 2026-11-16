<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Classification\Model;

use InvalidArgumentException;
use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\BiomeFilter;
use MapClassifier\Map\Classification\Model\ErrorCode;
use MapClassifier\Map\Classification\Model\MapClassification;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Tests\ResponseFixtures;
use PHPUnit\Framework\TestCase;

final class BiomeFilterTest extends TestCase
{
    public function testParse(): void
    {
        $this->assertSame(Biome::Snow, BiomeFilter::parse('snow')->biome);
        $this->assertNull(BiomeFilter::parse('none')->biome);
    }

    public function testParseInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid biome "ocean", expected "none" or one of: forest, jungle');

        BiomeFilter::parse('ocean');
    }

    public function testMatches(): void
    {
        $snow = MapResult::success(1, 'v', MapClassification::fromArray(ResponseFixtures::classification('snow')), 'luna');
        $interior = MapResult::success(2, 'v', MapClassification::fromArray(['biome' => null] + ResponseFixtures::classification()), 'luna');
        $error = MapResult::failure(3, 'v', ErrorCode::MissingKey);

        $this->assertTrue(new BiomeFilter(Biome::Snow)->matches($snow));
        $this->assertFalse(new BiomeFilter(Biome::Forest)->matches($snow));
        $this->assertFalse(new BiomeFilter(Biome::Snow)->matches($interior));
        $this->assertTrue(new BiomeFilter(null)->matches($interior));
        $this->assertFalse(new BiomeFilter(null)->matches($snow));
        $this->assertFalse(new BiomeFilter(null)->matches($error), 'Errors have no biome, but are not classified');
    }
}
