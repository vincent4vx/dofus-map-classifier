<?php

declare(strict_types=1);

namespace MapClassifier\Classification;

use MapClassifier\Batch\BatchResultParser;
use MapClassifier\Batch\ImageEncoder;
use MapClassifier\Map\MapFile;
use MapClassifier\OpenAI\ConcurrentResponsesClient;
use MapClassifier\Storage\ErrorCode;
use MapClassifier\Storage\MapResult;
use MapClassifier\Storage\ResultStorage;
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
        private BatchResultParser $parser,
        private ResultStorage $results,
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
