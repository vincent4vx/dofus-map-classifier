<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Service;

use MapClassifier\Map\Service\MapKeyDownloader;
use MapClassifier\Tests\TempDirTrait;
use PharData;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function file_get_contents;
use function scandir;

final class MapKeyDownloaderTest extends TestCase
{
    use TempDirTrait;

    public function testDownload(): void
    {
        $tar = new PharData($this->tempDir() . '/archive.tar');
        $tar->addFromString('MapKeys-DR-main/README.md', 'readme');
        $tar->addFromString('MapKeys-DR-main/maps/10_0706131721.txt', 'key10');
        $tar->addFromString('MapKeys-DR-main/maps/11_0905131019.txt', 'key11');
        $tar->compress(\Phar::GZ);

        $client = new MockHttpClient(new MockResponse((string) file_get_contents($this->tempDir() . '/archive.tar.gz')));
        $target = $this->tempDir() . '/keys';

        $this->assertSame(2, (new MapKeyDownloader($client))->download('https://example.com/keys.tar.gz', $target));
        $this->assertSame(['.', '..', '10_0706131721.txt', '11_0905131019.txt'], scandir($target));
        $this->assertSame('key10', file_get_contents($target . '/10_0706131721.txt'));
    }

    public function testDownloadHttpError(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 404]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 404');

        (new MapKeyDownloader($client))->download('https://example.com/keys.tar.gz', $this->tempDir() . '/keys');
    }
}
