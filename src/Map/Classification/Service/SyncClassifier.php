<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Service;

use MapClassifier\Infrastructure\OpenAI\ConcurrentResponsesClient;
use MapClassifier\Map\Classification\Model\ErrorCode;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Model\MapFile;
use Throwable;

/**
 * Classify maps by calling directly the Responses API, with concurrent requests
 */
final readonly class SyncClassifier
{
    public function __construct(
        private ConcurrentResponsesClient $client,
        private ClassificationPrompt $prompt,
        private ImageEncoder $encoder,
        private MapResultParser $parser,
        private MapResultRepository $results,
        private string $rendersDir,
        private string $model,
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
        $requests = (function () use ($maps) {
            foreach ($maps as $map) {
                yield $map => fn () => $this->prompt->requestBody($map->id, $this->encoder->dataUrl($this->rendersDir . '/' . $map->id . '.png'));
            }
        })();

        $this->client->run($requests, $concurrency, function (MapFile $map, int $status, array $body, ?Throwable $error) use ($onResult) {
            $result = $error !== null
                ? MapResult::failure($map->id, $map->version, ErrorCode::ClassificationFailed, $error->getMessage(), $this->model)
                : $this->parser->parseResponse($map->id, $map->version, $status, $body, $this->model);

            $this->results->save($result);

            if ($onResult) {
                $onResult($result);
            }
        });
    }
}
