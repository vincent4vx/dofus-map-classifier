<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Repository;

use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Repository\MapKeyRepository;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

use function file_put_contents;

final class MapKeyRepositoryTest extends TestCase
{
    use TempDirTrait;

    public function testFind(): void
    {
        file_put_contents($this->tempDir() . '/10_0706131721.txt', "abcdef\n");
        $repository = new MapKeyRepository($this->tempDir());

        $this->assertSame('abcdef', $repository->find(new MapFile(10, '0706131721', true, 'x'))?->key);
        $this->assertNull($repository->find(new MapFile(10, '0905131019', true, 'x')));
        $this->assertNull($repository->find(new MapFile(11, '0706131721', true, 'x')));
    }
}
