<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Service;

use InvalidArgumentException;
use MapClassifier\Infrastructure\OpenAI\ResponseFailure;
use MapClassifier\Infrastructure\OpenAI\StructuredResponse;
use MapClassifier\Map\Classification\Model\ErrorCode;
use MapClassifier\Map\Classification\Model\MapClassification;
use MapClassifier\Map\Classification\Model\MapResult;
use Throwable;

/**
 * Parse a Responses API response into a map result
 */
final readonly class MapResultParser
{
    /**
     * @param int $statusCode HTTP status (0 on transport error)
     * @param array<array-key, mixed> $body Response body
     * @param string $model Requested model
     * @param Throwable|null $error Transport error
     */
    public function parse(int $id, ?string $version, int $statusCode, array $body, string $model, ?Throwable $error = null): MapResult
    {
        $response = StructuredResponse::parse($statusCode, $body, $error, $model);

        if ($response->output !== null) {
            try {
                return MapResult::success($id, $version, MapClassification::fromArray($response->output), $response->model);
            } catch (InvalidArgumentException $e) {
                $response = StructuredResponse::invalidOutput($e->getMessage(), $response->model);
            }
        }

        return MapResult::failure(
            $id,
            $version,
            $response->failure === ResponseFailure::Refused ? ErrorCode::Refused : ErrorCode::ClassificationFailed,
            $response->message,
            $response->model,
        );
    }
}
