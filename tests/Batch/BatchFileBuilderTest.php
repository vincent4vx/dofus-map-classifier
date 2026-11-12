<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Batch;

use MapClassifier\Batch\BatchFileBuilder;
use MapClassifier\Batch\ImageEncoder;
use MapClassifier\Classification\ClassificationPrompt;
use MapClassifier\Map\MapFile;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

use function array_map;
use function base64_decode;
use function explode;
use function file;
use function getimagesizefromstring;
use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagefill;
use function imagepng;
use function file_get_contents;
use function json_decode;
use function strlen;

use const FILE_IGNORE_NEW_LINES;

final class BatchFileBuilderTest extends TestCase
{
    use TempDirTrait;

    /** @var list<MapFile> */
    private array $maps;

    protected function setUp(): void
    {
        $this->maps = [];

        foreach ([1, 2, 3] as $id) {
            $img = imagecreatetruecolor(742, 432);
            imagefill($img, 0, 0, imagecolorallocate($img, 10 * $id, 100, 50));
            imagepng($img, $this->tempDir() . '/' . $id . '.png');
            $this->maps[] = new MapFile($id, 'v' . $id, true, 'unused');
        }
    }

    public function testBuildSingleFile(): void
    {
        $files = $this->builder()->build($this->maps, $this->renderPath(...), $this->tempDir() . '/out', 'test');

        $this->assertCount(1, $files);
        $this->assertSame($this->tempDir() . '/out/test-001.jsonl', $files[0]->path);
        $this->assertSame([1 => 'v1', 2 => 'v2', 3 => 'v3'], $files[0]->maps);

        $lines = array_map(static fn (string $line) => json_decode($line, true), file($files[0]->path, FILE_IGNORE_NEW_LINES));

        $this->assertCount(3, $lines);
        $this->assertSame('map-1', $lines[0]['custom_id']);
        $this->assertSame('POST', $lines[0]['method']);
        $this->assertSame('/v1/responses', $lines[0]['url']);
        $this->assertSame('luna', $lines[0]['body']['model']);

        $image = $lines[0]['body']['input'][0]['content'][1]['image_url'];
        $this->assertStringStartsWith('data:image/jpeg;base64,', $image);
        $size = getimagesizefromstring(base64_decode(explode(',', $image)[1]));
        $this->assertSame([1484, 864], [$size[0], $size[1]]);
    }

    public function testNativeScale(): void
    {
        $files = (new BatchFileBuilder(new ClassificationPrompt('luna'), new ImageEncoder(ImageEncoder::JPEG, 1.0)))
            ->build([$this->maps[0]], $this->renderPath(...), $this->tempDir() . '/out', 'test');

        $image = json_decode(file($files[0]->path)[0], true)['body']['input'][0]['content'][1]['image_url'];
        $size = getimagesizefromstring(base64_decode(explode(',', $image)[1]));
        $this->assertSame([742, 432], [$size[0], $size[1]]);
    }

    public function testPngFormat(): void
    {
        $files = (new BatchFileBuilder(new ClassificationPrompt('luna'), new ImageEncoder(ImageEncoder::PNG, 1.5)))
            ->build([$this->maps[0]], $this->renderPath(...), $this->tempDir() . '/out', 'test');

        $line = json_decode(file($files[0]->path)[0], true);
        $image = $line['body']['input'][0]['content'][1]['image_url'];
        $this->assertStringStartsWith('data:image/png;base64,', $image);
        $size = getimagesizefromstring(base64_decode(explode(',', $image)[1]));
        $this->assertSame([1113, 648], [$size[0], $size[1]]);
    }

    public function testSplitByRequestCount(): void
    {
        $files = $this->builder(maxRequests: 2)->build($this->maps, $this->renderPath(...), $this->tempDir() . '/out', 'test');

        $this->assertCount(2, $files);
        $this->assertSame([1 => 'v1', 2 => 'v2'], $files[0]->maps);
        $this->assertSame([3 => 'v3'], $files[1]->maps);
        $this->assertSame($this->tempDir() . '/out/test-002.jsonl', $files[1]->path);
    }

    public function testSplitBySize(): void
    {
        // One line is larger than the limit: one file per map
        $files = $this->builder(maxFileSize: 10)->build($this->maps, $this->renderPath(...), $this->tempDir() . '/out', 'test');

        $this->assertCount(3, $files);
        $this->assertSame([3 => 'v3'], $files[2]->maps);
        $this->assertSame(strlen((string) file_get_contents($files[2]->path)), $files[2]->size);
    }

    public function testEmpty(): void
    {
        $this->assertSame([], $this->builder()->build([], $this->renderPath(...), $this->tempDir() . '/out', 'test'));
    }

    private function builder(int $maxFileSize = 1_000_000, int $maxRequests = 100): BatchFileBuilder
    {
        return new BatchFileBuilder(new ClassificationPrompt('luna'), new ImageEncoder(), $maxFileSize, $maxRequests);
    }

    private function renderPath(MapFile $map): string
    {
        return $this->tempDir() . '/' . $map->id . '.png';
    }
}
