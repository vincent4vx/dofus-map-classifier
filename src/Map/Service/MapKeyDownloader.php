<?php

declare(strict_types=1);

namespace MapClassifier\Map\Service;

use PharData;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function copy;
use function fclose;
use function fopen;
use function fwrite;
use function is_dir;
use function mkdir;
use function preg_match;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Download map keys from the MapKeys-DR repository archive
 */
final readonly class MapKeyDownloader
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    /**
     * Download the tar.gz archive and extract all "maps/{id}_{version}.txt" files into $targetDir
     *
     * @return int Number of extracted keys
     */
    public function download(string $archiveUrl, string $targetDir): int
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mapkeys');
        $archive = $tmp . '.tar.gz';

        try {
            $this->fetch($archiveUrl, $archive);

            return $this->extract($archive, $targetDir);
        } finally {
            @unlink($tmp);
            @unlink($archive);
        }
    }

    public function extract(string $archive, string $targetDir): int
    {
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Cannot create directory ' . $targetDir);
        }

        $count = 0;

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new PharData($archive)) as $file) {
            if (!preg_match('#/maps/(\d+_[^/]+\.txt)$#', $file->getPathname(), $matches)) {
                continue;
            }

            if (!copy($file->getPathname(), $targetDir . '/' . $matches[1])) {
                throw new RuntimeException('Cannot write key ' . $matches[1]);
            }

            ++$count;
        }

        return $count;
    }

    private function fetch(string $url, string $target): void
    {
        $response = $this->httpClient->request('GET', $url);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Cannot download map keys from ' . $url . ': HTTP ' . $response->getStatusCode());
        }

        $handle = fopen($target, 'wb') ?: throw new RuntimeException('Cannot open ' . $target);

        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($handle, $chunk->getContent());
            }
        } finally {
            fclose($handle);
        }
    }
}
