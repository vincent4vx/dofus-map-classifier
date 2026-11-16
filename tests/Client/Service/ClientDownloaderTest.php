<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Client\Service;

use MapClassifier\Client\Service\ClientDownloader;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use ZipArchive;

use function file_get_contents;
use function is_file;

final class ClientDownloaderTest extends TestCase
{
    use TempDirTrait;

    public function testDownloadOnlyMapsAndSprites(): void
    {
        $client = new MockHttpClient(new MockResponse($this->archive()));
        $target = $this->tempDir() . '/dofus';

        $this->assertSame(3, (new ClientDownloader($client))->download('https://example.com/client.zip', $target));
        $this->assertSame('map', file_get_contents($target . '/data/maps/10_0706131721X.swf'));
        $this->assertTrue(is_file($target . '/clips/gfx/g1.swf'));
        $this->assertTrue(is_file($target . '/clips/gfx/o1.swf'));
        $this->assertFalse(is_file($target . '/Dofus.exe'));
        $this->assertFalse(is_file($target . '/clips/items/1.swf'));
        $this->assertFalse(is_file($target . '/data/maps.swf'));
    }

    public function testDownloadAll(): void
    {
        $client = new MockHttpClient(new MockResponse($this->archive()));
        $target = $this->tempDir() . '/dofus';

        $this->assertSame(6, (new ClientDownloader($client))->download('https://example.com/client.zip', $target, null));
        $this->assertTrue(is_file($target . '/Dofus.exe'));
        $this->assertTrue(is_file($target . '/clips/items/1.swf'));
    }

    public function testDownloadHttpError(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 404]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot download the client from https://example.com/client.zip: HTTP 404');

        (new ClientDownloader($client))->download('https://example.com/client.zip', $this->tempDir() . '/dofus');
    }

    public function testInvalidArchive(): void
    {
        $client = new MockHttpClient(new MockResponse('not a zip'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid client archive');

        (new ClientDownloader($client))->download('https://example.com/client.zip', $this->tempDir() . '/dofus');
    }

    public function testArchiveWithoutClientFiles(): void
    {
        $zip = new ZipArchive();
        $zip->open($this->tempDir() . '/empty.zip', ZipArchive::CREATE);
        $zip->addFromString('README.md', 'readme');
        $zip->close();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No client file found in the archive');

        (new ClientDownloader(new MockHttpClient()))->extract($this->tempDir() . '/empty.zip', $this->tempDir() . '/dofus');
    }

    private function archive(): string
    {
        $file = $this->tempDir() . '/client.zip';
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE);
        $zip->addFromString('Dofus.exe', 'exe');
        $zip->addEmptyDir('data/maps');
        $zip->addFromString('data/maps/10_0706131721X.swf', 'map');
        $zip->addFromString('data/maps.swf', 'not in the maps directory');
        $zip->addFromString('clips/gfx/g1.swf', 'ground');
        $zip->addFromString('clips/gfx/o1.swf', 'object');
        $zip->addFromString('clips/items/1.swf', 'item');
        $zip->close();

        return (string) file_get_contents($file);
    }
}
