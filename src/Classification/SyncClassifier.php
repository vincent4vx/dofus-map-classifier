<?php

declare(strict_types=1);

namespace MapClassifier\Classification;

use Closure;
use MapClassifier\Batch\BatchResultParser;
use MapClassifier\Batch\ImageEncoder;
use MapClassifier\Map\MapFile;
use MapClassifier\Storage\ErrorCode;
use MapClassifier\Storage\MapResult;
use MapClassifier\Storage\ResultStorage;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function array_shift;
use function count;
use function is_array;
use function spl_object_id;

/**
 * Classify maps by calling directly the Responses API, with concurrent requests
 */
final readonly class SyncClassifier
{
    public function __construct(
        /** Should handle retries (e.g. RetryableHttpClient) */
        private HttpClientInterface $httpClient,
        /** @var Closure(): string */
        private Closure $apiKey,
        private string $baseUrl,
        private ClassificationPrompt $prompt,
        private ImageEncoder $encoder,
        private BatchResultParser $parser,
        private ResultStorage $results,
        private string $rendersDir,
        private string $model,
        private float $timeout = 300,
    ) {}

    /**
     * Classify maps and save results
     *
     * @param list<MapFile> $maps
     * @param positive-int $concurrency Maximum number of requests in flight
     * @param callable(MapResult): void|null $onResult Called after each result is saved
     */
    public function classify(array $maps, int $concurrency = 5, ?callable $onResult = null): void
    {
        $apiKey = ($this->apiKey)();

        /** @var array<int, ResponseInterface> $active */
        $active = [];

        while ($maps || $active) {
            while ($maps && count($active) < $concurrency) {
                $map = array_shift($maps);
                $response = $this->httpClient->request('POST', $this->baseUrl . '/responses', [
                    'auth_bearer' => $apiKey,
                    'json' => $this->prompt->requestBody($map->id, $this->encoder->dataUrl($this->rendersDir . '/' . $map->id . '.png')),
                    'timeout' => $this->timeout,
                    'user_data' => $map,
                ]);
                $active[spl_object_id($response)] = $response;
            }

            // Wait for the first completed response, then fill the pool again
            foreach ($this->httpClient->stream($active) as $response => $chunk) {
                try {
                    // Read the status code to mark it as checked: errors are handled by the parser
                    if ($chunk->isFirst()) {
                        $response->getStatusCode();
                    }

                    if (!$chunk->isLast()) {
                        continue;
                    }

                    $result = $this->result($response);
                } catch (ExceptionInterface $e) {
                    $map = $response->getInfo('user_data');
                    $result = MapResult::failure($map->id, $map->version, ErrorCode::ClassificationFailed, $e->getMessage(), $this->model);
                }

                unset($active[spl_object_id($response)]);
                $this->results->save($result);

                if ($onResult) {
                    $onResult($result);
                }

                break;
            }
        }
    }

    private function result(ResponseInterface $response): MapResult
    {
        /** @var MapFile $map */
        $map = $response->getInfo('user_data');
        $body = $response->toArray(false);

        return $this->parser->parseResponse($map->id, $map->version, $response->getStatusCode(), is_array($body) ? $body : [], $this->model);
    }
}
