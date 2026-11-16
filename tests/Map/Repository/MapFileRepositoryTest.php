<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Repository;

use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Repository\MapFileRepository;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

use function touch;

final class MapFileRepositoryTest extends TestCase
{
    use TempDirTrait;

    public function testAll(): void
    {
        $dir = $this->tempDir();
        touch($dir . '/10_0706131721X.swf');
        touch($dir . '/2_0905131019.swf');
        touch($dir . '/invalid.swf');
        touch($dir . '/3_0706131721X.txt');

        $repository = new MapFileRepository($dir);

        $this->assertEquals([
            2 => new MapFile(2, '0905131019', false, $dir . '/2_0905131019.swf'),
            10 => new MapFile(10, '0706131721', true, $dir . '/10_0706131721X.swf'),
        ], $repository->all());
        $this->assertSame([2, 10], array_keys($repository->all()));
        $this->assertEquals(new MapFile(10, '0706131721', true, $dir . '/10_0706131721X.swf'), $repository->get(10));
        $this->assertNull($repository->get(3));
    }
}
