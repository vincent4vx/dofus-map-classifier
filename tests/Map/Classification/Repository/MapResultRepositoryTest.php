<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Classification\Repository;

use MapClassifier\Map\Classification\Model\ErrorCode;
use MapClassifier\Map\Classification\Model\MapClassification;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Tests\BatchFixtures;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function file_get_contents;
use function json_decode;

final class MapResultRepositoryTest extends TestCase
{
    use TempDirTrait;

    public function testSaveAndGet(): void
    {
        $storage = new MapResultRepository($this->tempDir() . '/results');
        $result = MapResult::success(42, 'v1', MapClassification::fromArray(BatchFixtures::classification()), 'luna');

        $this->assertNull($storage->get(42));
        $storage->save($result);

        $loaded = $storage->get(42);
        $this->assertNotNull($loaded);
        $this->assertTrue($loaded->ok);
        $this->assertEquals($result->classification, $loaded->classification);
        $this->assertSame($result->toArray(), $loaded->toArray());

        $this->assertSame([
            'id' => 42,
            'version' => 'v1',
            'status' => 'ok',
            'error' => null,
            'error_message' => null,
            'biome' => 'forest',
            'biome_other' => null,
            'indoor' => false,
            'location_type' => 'wild',
            'tags' => ['trees', 'river'],
            'description' => 'A forest crossed by a river.',
            'model' => 'luna',
            'classified_at' => $result->classifiedAt?->format(DATE_ATOM),
        ], json_decode((string) file_get_contents($this->tempDir() . '/results/42.json'), true));
    }

    public function testError(): void
    {
        $storage = new MapResultRepository($this->tempDir());
        $storage->save(MapResult::failure(3, 'v', ErrorCode::MissingKey, 'no key'));

        $loaded = $storage->get(3);
        $this->assertNotNull($loaded);
        $this->assertFalse($loaded->ok);
        $this->assertSame(ErrorCode::MissingKey, $loaded->error);
        $this->assertSame('no key', $loaded->errorMessage);
        $this->assertNull($loaded->classification);
        $this->assertSame('error', $loaded->toArray()['status']);
    }

    public function testAllAndDelete(): void
    {
        $storage = new MapResultRepository($this->tempDir());
        $storage->save(MapResult::failure(10, 'v', ErrorCode::MissingKey));
        $storage->save(MapResult::failure(2, 'v', ErrorCode::MissingKey));

        $this->assertSame([2, 10], array_keys($storage->all()));

        $storage->delete(10);
        $this->assertSame([2], array_keys($storage->all()));
    }
}
