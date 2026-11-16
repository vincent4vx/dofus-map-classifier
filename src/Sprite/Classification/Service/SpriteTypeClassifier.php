<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Service;

use InvalidArgumentException;
use MapClassifier\Infrastructure\OpenAI\ConcurrentResponsesClient;
use MapClassifier\Infrastructure\OpenAI\ResponseFailure;
use MapClassifier\Infrastructure\OpenAI\StructuredResponse;
use MapClassifier\Sprite\Classification\Model\SpriteType;
use MapClassifier\Sprite\Classification\Model\SpriteTypeError;
use MapClassifier\Sprite\Classification\Model\SpriteTypeResult;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Repository\SpriteImageRepository;
use Throwable;

use function base64_encode;

/**
 * Find the type of sprites by calling the Responses API, with concurrent requests
 */
final readonly class SpriteTypeClassifier
{
    public function __construct(
        private ConcurrentResponsesClient $client,
        private SpriteTypePrompt $prompt,
        private SpriteImageRepository $sprites,
        private SpriteTypeRepository $storage,
        private string $model,
    ) {}

    /**
     * @param list<SpriteProfile> $profiles
     * @param positive-int $concurrency
     * @param callable(SpriteTypeResult): void|null $onResult Called after each result is saved
     */
    public function classify(array $profiles, int $concurrency = 5, ?callable $onResult = null): void
    {
        $requests = (function () use ($profiles, $onResult) {
            foreach ($profiles as $profile) {
                $png = $this->sprites->read($profile->set, $profile->id);
                $size = $this->sprites->size($profile->set, $profile->id);

                if ($png === null || $size === null) {
                    $this->save(SpriteTypeResult::failure($profile->set, $profile->id, SpriteTypeError::ImageNotExported), $onResult);
                    continue;
                }

                yield $profile => fn () => $this->prompt->requestBody($profile, 'data:image/png;base64,' . base64_encode($png), $size);
            }
        })();

        $this->client->run($requests, $concurrency, function (SpriteProfile $profile, int $status, array $body, ?Throwable $error) use ($onResult) {
            $this->save($this->result($profile, $status, $body, $error), $onResult);
        });
    }

    /**
     * @param int $status HTTP status (0 on transport error)
     * @param array<array-key, mixed> $body Response body
     * @param Throwable|null $error Transport error
     */
    public function result(SpriteProfile $profile, int $status, array $body, ?Throwable $error = null): SpriteTypeResult
    {
        $response = StructuredResponse::parse($status, $body, $error, $this->model);

        if ($response->output !== null) {
            try {
                return SpriteTypeResult::success($profile->set, $profile->id, SpriteType::fromArray($response->output), $response->model);
            } catch (InvalidArgumentException $e) {
                $response = StructuredResponse::invalidOutput($e->getMessage(), $response->model);
            }
        }

        return SpriteTypeResult::failure(
            $profile->set,
            $profile->id,
            $response->failure === ResponseFailure::Refused ? SpriteTypeError::Refused : SpriteTypeError::ClassificationFailed,
            $response->message,
            $response->model,
        );
    }

    /**
     * @param callable(SpriteTypeResult): void|null $onResult
     */
    private function save(SpriteTypeResult $result, ?callable $onResult): void
    {
        $this->storage->save($result);

        if ($onResult) {
            $onResult($result);
        }
    }
}
