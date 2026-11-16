<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Service;

use MapClassifier\Infrastructure\OpenAI\ConcurrentResponsesClient;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Model\MapFile;
use Throwable;

/**
 * Classify maps by calling directly the Responses API, with concurrent requests
 */
final readonly class MapClassifier
{
    public function __construct(
        private ConcurrentResponsesClient $client,
        private ClassificationPrompt $prompt,
        private MapResultParser $parser,
        private MapResultRepository $results,
        private string $rendersDir,
        private string $model,
    ) {}

    /**
     * Classify maps and save results
     *
     * @param list<MapFile> $maps
     * @param ImageEncoder $encoder Encode the rendered map sent to the model
     * @param positive-int $concurrency Maximum number of requests in flight
     * @param callable(MapResult): void|null $onResult Called after each result is saved
     */
    public function classify(array $maps, ImageEncoder $encoder = new ImageEncoder(), int $concurrency = 5, ?callable $onResult = null): void
    {
        $requests = (function () use ($maps, $encoder) {
            foreach ($maps as $map) {
                yield $map => fn () => $this->prompt->requestBody($map->id, $encoder->dataUrl($this->rendersDir . '/' . $map->id . '.png'));
            }
        })();

        $this->client->run($requests, $concurrency, function (MapFile $map, int $status, array $body, ?Throwable $error) use ($onResult) {
            $result = $this->parser->parse($map->id, $map->version, $status, $body, $this->model, $error);

            $this->results->save($result);

            if ($onResult) {
                $onResult($result);
            }
        });
    }
}
