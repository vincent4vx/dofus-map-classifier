<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\OpenAI;

use Override;
use RuntimeException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function fclose;
use function fopen;
use function fwrite;
use function is_string;
use function rename;

/**
 * HTTP implementation of the OpenAI Batch API
 */
final readonly class OpenAIClient implements BatchApiInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.openai.com/v1',
    ) {}

    #[Override]
    public function uploadBatchFile(string $path): string
    {
        $form = new FormDataPart([
            'purpose' => 'batch',
            'file' => DataPart::fromPath($path, contentType: 'application/jsonl'),
        ]);

        $data = $this->json($this->httpClient->request('POST', $this->baseUrl . '/files', [
            'auth_bearer' => $this->apiKey,
            'headers' => $form->getPreparedHeaders()->toArray(),
            'body' => $form->bodyToIterable(),
            'timeout' => 600,
        ]));

        return is_string($data['id'] ?? null) ? $data['id'] : throw new RuntimeException('Invalid upload response');
    }

    #[Override]
    public function createBatch(string $inputFileId, string $endpoint, array $metadata = []): BatchInfo
    {
        $body = [
            'input_file_id' => $inputFileId,
            'endpoint' => $endpoint,
            'completion_window' => '24h',
        ];

        if ($metadata) {
            $body['metadata'] = $metadata;
        }

        return BatchInfo::fromApi($this->json($this->httpClient->request('POST', $this->baseUrl . '/batches', [
            'auth_bearer' => $this->apiKey,
            'json' => $body,
        ])));
    }

    #[Override]
    public function getBatch(string $batchId): BatchInfo
    {
        return BatchInfo::fromApi($this->json($this->httpClient->request('GET', $this->baseUrl . '/batches/' . $batchId, [
            'auth_bearer' => $this->apiKey,
        ])));
    }

    #[Override]
    public function downloadFile(string $fileId, string $target): void
    {
        $response = $this->httpClient->request('GET', $this->baseUrl . '/files/' . $fileId . '/content', [
            'auth_bearer' => $this->apiKey,
            'timeout' => 600,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Cannot download file ' . $fileId . ': ' . $response->getContent(false));
        }

        $tmp = $target . '.tmp';
        $handle = fopen($tmp, 'wb') ?: throw new RuntimeException('Cannot open ' . $tmp);

        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($handle, $chunk->getContent());
            }
        } finally {
            fclose($handle);
        }

        rename($tmp, $target);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        if ($response->getStatusCode() >= 300) {
            throw new RuntimeException('OpenAI API error (HTTP ' . $response->getStatusCode() . '): ' . $response->getContent(false));
        }

        return $response->toArray();
    }
}
