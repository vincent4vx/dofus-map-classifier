<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\OpenAI;

use Closure;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

use function count;
use function spl_object_id;

/**
 * Call the Responses API with several requests in flight
 * Retries should be handled by the HTTP client (e.g. RetryableHttpClient)
 */
final readonly class ConcurrentResponsesClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        /** @var Closure(): string */
        private Closure $apiKey,
        private string $baseUrl = 'https://api.openai.com/v1',
        private float $timeout = 300,
    ) {}

    /**
     * Send the requests, and call $onResponse for each response, in completion order
     *
     * @template K
     * @param iterable<K, Closure(): array<string, mixed>> $requests Request body factories, indexed by a key identifying the request
     * @param positive-int $concurrency Maximum number of requests in flight
     * @param callable(K $key, int $status, array<array-key, mixed> $body, ?Throwable $error): void $onResponse
     *     Status is 0 and body is empty on transport error or invalid response
     */
    public function run(iterable $requests, int $concurrency, callable $onResponse): void
    {
        $apiKey = ($this->apiKey)();
        $queue = (static fn () => yield from $requests)();

        /** @var array<int, ResponseInterface> $active */
        $active = [];

        while ($queue->valid() || $active) {
            while ($queue->valid() && count($active) < $concurrency) {
                $key = $queue->key();
                $body = ($queue->current())();
                $queue->next();

                $response = $this->httpClient->request('POST', $this->baseUrl . '/responses', [
                    'auth_bearer' => $apiKey,
                    'json' => $body,
                    'timeout' => $this->timeout,
                    'user_data' => $key,
                ]);
                $active[spl_object_id($response)] = $response;
            }

            // Wait for the first completed response, then fill the pool again
            foreach ($this->httpClient->stream($active) as $response => $chunk) {
                try {
                    // Read the status code to mark it as checked: errors are handled by the caller
                    if ($chunk->isFirst()) {
                        $response->getStatusCode();
                    }

                    if (!$chunk->isLast()) {
                        continue;
                    }

                    $result = [$response->getStatusCode(), $response->toArray(false), null];
                } catch (ExceptionInterface $e) {
                    $result = [0, [], $e];
                }

                unset($active[spl_object_id($response)]);
                $onResponse($response->getInfo('user_data'), ...$result);

                break;
            }
        }
    }
}
