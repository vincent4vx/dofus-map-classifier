<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Shore;

use InvalidArgumentException;
use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Infrastructure\OpenAI\ConcurrentResponsesClient;
use MapClassifier\Infrastructure\OpenAI\StructuredResponse;
use MapClassifier\Map\Classification\Service\ImageEncoder;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Repository\SpriteRepository;
use Throwable;

use function base64_encode;
use function is_file;
use function sprintf;

/**
 * Find the role of shore pieces in the shores (see {@see ShoreRolePrompt}), and save it in {directory}/o{id}.json:
 * {"id": int, "model": string, "role": {@see ShoreRole} | null, "error": string | null}
 */
final readonly class ShoreRoleClassifier
{
    public function __construct(
        private ConcurrentResponsesClient $client,
        private ShoreRolePrompt $prompt,
        private SpriteRepository $sprites,
        private string $model,
        /** The examples along the water (shores:examples) */
        private string $examplesDir,
        /** Output directory */
        private string $directory,
        private ImageEncoder $exampleEncoder = new ImageEncoder(),
    ) {}

    /**
     * @param list<SpriteProfile> $profiles
     * @param positive-int $concurrency
     * @param callable(SpriteProfile, ?ShoreRole, ?string): void|null $onResult Called after each result is saved, with the role or the error
     */
    public function classify(array $profiles, int $concurrency = 5, ?callable $onResult = null): void
    {
        $requests = (function () use ($profiles, $onResult) {
            foreach ($profiles as $profile) {
                $metadata = $this->sprites->get(SpriteSet::Objects, $profile->id);
                $png = $metadata !== null ? $this->sprites->image($metadata) : null;
                $example = sprintf('%s/o%d.png', $this->examplesDir, $profile->id);

                if ($metadata === null || $png === null || !is_file($example)) {
                    $this->save($profile, null, 'No image or no example along the water: run sprites:images and shores:examples', $this->model, $onResult);

                    continue;
                }

                yield $profile => fn () => $this->prompt->requestBody($profile, $metadata, 'data:image/png;base64,' . base64_encode($png), $this->exampleEncoder->dataUrl($example));
            }
        })();

        $this->client->run($requests, $concurrency, function (SpriteProfile $profile, int $status, array $body, ?Throwable $error) use ($onResult) {
            $response = StructuredResponse::parse($status, $body, $error, $this->model);

            if ($response->output !== null) {
                try {
                    $this->save($profile, ShoreRole::fromArray($response->output), null, $response->model, $onResult);

                    return;
                } catch (InvalidArgumentException $e) {
                    $response = StructuredResponse::invalidOutput($e->getMessage(), $response->model);
                }
            }

            $this->save($profile, null, $response->message ?? 'Classification failed', $response->model, $onResult);
        });
    }

    /**
     * @param callable(SpriteProfile, ?ShoreRole, ?string): void|null $onResult
     */
    private function save(SpriteProfile $profile, ?ShoreRole $role, ?string $error, string $model, ?callable $onResult): void
    {
        JsonFile::write(sprintf('%s/o%d.json', $this->directory, $profile->id), ['id' => $profile->id, 'model' => $model, 'role' => $role, 'error' => $error]);

        if ($onResult) {
            $onResult($profile, $role, $error);
        }
    }
}
