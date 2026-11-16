<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Service;

use InvalidArgumentException;
use JsonException;
use MapClassifier\Infrastructure\OpenAI\ConcurrentResponsesClient;
use MapClassifier\Infrastructure\OpenAI\ResponseOutput;
use MapClassifier\Sprite\Classification\Model\SpriteType;
use MapClassifier\Sprite\Classification\Model\SpriteTypeResult;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Repository\SpriteImageRepository;
use Throwable;

use function base64_encode;
use function file_get_contents;
use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

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
                $png = @file_get_contents($this->sprites->path($profile->set, $profile->id));
                $size = $this->sprites->size($profile->set, $profile->id);

                if ($png === false || $size === null) {
                    $this->save(SpriteTypeResult::failure($profile->set, $profile->id, 'Sprite image not exported'), $onResult);
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
     * @param array<array-key, mixed> $body
     */
    public function result(SpriteProfile $profile, int $status, array $body, ?Throwable $error = null): SpriteTypeResult
    {
        if ($error !== null) {
            return SpriteTypeResult::failure($profile->set, $profile->id, $error->getMessage(), $this->model);
        }

        if ($status !== 200) {
            return SpriteTypeResult::failure($profile->set, $profile->id, ResponseOutput::error($status, $body), $this->model);
        }

        $output = ResponseOutput::fromBody($body);
        $model = $output->model ?? $this->model;

        if ($output->refusal !== null) {
            return SpriteTypeResult::failure($profile->set, $profile->id, 'Refused: ' . $output->refusal, $model);
        }

        if ($output->text === null) {
            return SpriteTypeResult::failure($profile->set, $profile->id, 'No output text (response status: ' . $output->status . ')', $model);
        }

        try {
            $data = json_decode($output->text, true, flags: JSON_THROW_ON_ERROR);

            return SpriteTypeResult::success($profile->set, $profile->id, SpriteType::fromArray(is_array($data) ? $data : []), $model);
        } catch (JsonException|InvalidArgumentException $e) {
            return SpriteTypeResult::failure($profile->set, $profile->id, 'Invalid output: ' . $e->getMessage(), $model);
        }
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
