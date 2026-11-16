<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\Http;

use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function fclose;
use function fopen;
use function fwrite;

/**
 * Download a file by streaming it to the disk
 */
final readonly class FileDownloader
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    /**
     * @param string $label Name of the downloaded resource, used in error messages
     *
     * @throws RuntimeException When the response is not a 200, or the file cannot be written
     */
    public function download(string $url, string $target, string $label = 'file'): void
    {
        $response = $this->httpClient->request('GET', $url);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Cannot download ' . $label . ' from ' . $url . ': HTTP ' . $response->getStatusCode());
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
