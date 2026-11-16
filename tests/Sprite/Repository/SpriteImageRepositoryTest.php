<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Repository;

use MapClassifier\Sprite\Model\RenderedSprite;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Model\SpriteSize;
use MapClassifier\Sprite\Repository\SpriteImageRepository;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function json_decode;

final class SpriteImageRepositoryTest extends TestCase
{
    use TempDirTrait;

    public function testSaveAndRead(): void
    {
        $files = new SpriteImageRepository($this->tempDir());

        $this->assertFalse($files->exists(SpriteSet::Objects, 12));
        $this->assertNull($files->size(SpriteSet::Objects, 12));

        $files->save(SpriteSet::Objects, 12, new RenderedSprite('png-12', 53.5, 27.0));
        $files->save(SpriteSet::Objects, 3, new RenderedSprite('png-3', 10.0, 20.5));
        $files->save(SpriteSet::Grounds, 12, new RenderedSprite('png-g12', 1.0, 2.0));

        $this->assertSame($this->tempDir() . '/objects/12.png', $files->path(SpriteSet::Objects, 12));
        $this->assertSame('png-12', file_get_contents($files->path(SpriteSet::Objects, 12)));
        $this->assertSame('png-12', $files->read(SpriteSet::Objects, 12));
        $this->assertNull($files->read(SpriteSet::Objects, 404));
        $this->assertEquals(new SpriteSize(53.5, 27.0), $files->size(SpriteSet::Objects, 12));

        // Sizes are only persisted on flush
        $this->assertFalse((new SpriteImageRepository($this->tempDir()))->exists(SpriteSet::Objects, 12));

        $files->flush(SpriteSet::Objects);
        $this->assertSame(['3' => [10, 20.5], '12' => [53.5, 27]], json_decode((string) file_get_contents($this->tempDir() . '/objects/sizes.json'), true));

        $reloaded = new SpriteImageRepository($this->tempDir());
        $this->assertTrue($reloaded->exists(SpriteSet::Objects, 12));
        $this->assertEquals(new SpriteSize(10.0, 20.5), $reloaded->size(SpriteSet::Objects, 3));
        $this->assertFalse($reloaded->exists(SpriteSet::Grounds, 12));

        // Saving more sprites keeps existing sizes
        $reloaded->save(SpriteSet::Objects, 5, new RenderedSprite('png-5', 1.0, 1.0));
        $reloaded->flush(SpriteSet::Objects);
        $this->assertCount(3, json_decode((string) file_get_contents($this->tempDir() . '/objects/sizes.json'), true));
    }
}
