<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Repository;

use MapClassifier\Sprite\Model\RenderedSprite;
use MapClassifier\Sprite\Model\SpriteMetadata;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Model\SpriteSize;
use MapClassifier\Sprite\Repository\SpriteRepository;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

use function array_find;
use function file_get_contents;
use function getenv;
use function is_dir;
use function json_decode;
use function unlink;

final class SpriteRepositoryTest extends TestCase
{
    use TempDirTrait;

    public function testSaveAndGet(): void
    {
        $sprites = new SpriteRepository('/not-used', $this->tempDir());

        $this->assertNull($sprites->get(SpriteSet::Objects, 12));

        $metadata = $sprites->save(SpriteSet::Objects, 12, new RenderedSprite('png-12', 53.5, 27.0), 1);
        $sprites->save(SpriteSet::Objects, 3, new RenderedSprite('png-3', 10.0, 20.5), 4);
        $sprites->save(SpriteSet::Grounds, 69, new RenderedSprite('png-g69', 1.0, 2.0), 15);

        $this->assertEquals(new SpriteMetadata(SpriteSet::Objects, 12, new SpriteSize(53.5, 27.0), 1, 'objects/12.png'), $metadata);
        $this->assertEquals($metadata, $sprites->get(SpriteSet::Objects, 12));
        $this->assertSame('png-12', file_get_contents($this->tempDir() . '/objects/12.png'));
        $this->assertSame('png-12', $sprites->image($metadata));

        // Metadata is only persisted on flush
        $this->assertNull(new SpriteRepository('/not-used', $this->tempDir())->get(SpriteSet::Objects, 12));

        $sprites->flush(SpriteSet::Objects);
        $this->assertSame([
            '3' => ['image' => 'objects/3.png', 'width' => 10, 'height' => 20.5, 'frames' => 4],
            '12' => ['image' => 'objects/12.png', 'width' => 53.5, 'height' => 27, 'frames' => 1],
        ], json_decode((string) file_get_contents($this->tempDir() . '/objects/metadata.json'), true));

        $reloaded = new SpriteRepository('/not-used', $this->tempDir());
        $this->assertEquals(new SpriteMetadata(SpriteSet::Objects, 3, new SpriteSize(10.0, 20.5), 4, 'objects/3.png'), $reloaded->get(SpriteSet::Objects, 3));
        $this->assertNull($reloaded->get(SpriteSet::Grounds, 69), 'Not flushed');

        // Saving more sprites keeps the existing metadata
        $reloaded->save(SpriteSet::Objects, 5, new RenderedSprite('png-5', 1.0, 1.0), 1);
        $reloaded->flush(SpriteSet::Objects);
        $this->assertCount(3, (array) json_decode((string) file_get_contents($this->tempDir() . '/objects/metadata.json'), true));

        // The image file is required
        unlink($this->tempDir() . '/objects/5.png');
        $this->assertNull(new SpriteRepository('/not-used', $this->tempDir())->get(SpriteSet::Objects, 5));
    }

    public function testSlopeFrames(): void
    {
        $this->assertTrue(new SpriteMetadata(SpriteSet::Grounds, 69, new SpriteSize(1.0, 1.0), 15, 'grounds/69.png')->hasSlopeFrames());
        $this->assertFalse(new SpriteMetadata(SpriteSet::Grounds, 1, new SpriteSize(1.0, 1.0), 1, 'grounds/1.png')->hasSlopeFrames());
        $this->assertFalse(new SpriteMetadata(SpriteSet::Objects, 69, new SpriteSize(1.0, 1.0), 15, 'objects/69.png')->hasSlopeFrames(), 'Only grounds');
    }

    public function testClientSprites(): void
    {
        $dofusPath = array_find(
            [getenv('DOFUS_PATH') ?: '/dofus', __DIR__ . '/../../../var/dofus'],
            static fn (string $path) => is_dir($path . '/clips/gfx'),
        ) ?? $this->markTestSkipped('Dofus client not available: set DOFUS_PATH, or run "bin/console client:download"');

        $sprites = [];

        foreach (new SpriteRepository($dofusPath, $this->tempDir())->client(SpriteSet::Grounds, [1 => true, 69 => true]) as $id => $load) {
            $sprites[$id] = $load()->bounds()->width() > 0;
        }

        $this->assertEquals([1 => true, 69 => true], $sprites, 'Order of the client files');
    }
}
