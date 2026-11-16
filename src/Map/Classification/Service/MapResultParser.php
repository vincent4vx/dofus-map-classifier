<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Service;

use InvalidArgumentException;
use JsonException;
use MapClassifier\Map\Classification\Model\ErrorCode;
use MapClassifier\Map\Classification\Model\MapClassification;
use MapClassifier\Map\Classification\Model\MapResult;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Parse a Responses API response into a map result
 */
final readonly class MapResultParser
{
    /**
     * Parse a Responses API response body
     *
     * @param array<array-key, mixed> $body
     */
    public function parse(int $id, ?string $version, int $statusCode, array $body, string $model): MapResult
    {
        if ($statusCode !== 200) {
            return MapResult::failure(
                $id,
                $version,
                ErrorCode::ClassificationFailed,
                'HTTP ' . ($statusCode ?: '?') . ': ' . (is_array($body['error'] ?? null) ? self::errorMessage($body['error']) : 'unknown error'),
                $model,
            );
        }

        $responseModel = is_string($body['model'] ?? null) ? $body['model'] : $model;

        foreach (is_array($body['output'] ?? null) ? $body['output'] : [] as $item) {
            if (!is_array($item) || ($item['type'] ?? null) !== 'message' || !is_array($item['content'] ?? null)) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (!is_array($content)) {
                    continue;
                }

                if (($content['type'] ?? null) === 'refusal') {
                    return MapResult::failure($id, $version, ErrorCode::Refused, (string) ($content['refusal'] ?? ''), $responseModel);
                }

                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    try {
                        $output = json_decode($content['text'], true, flags: JSON_THROW_ON_ERROR);

                        return MapResult::success($id, $version, MapClassification::fromArray(is_array($output) ? $output : []), $responseModel);
                    } catch (JsonException|InvalidArgumentException $e) {
                        return MapResult::failure($id, $version, ErrorCode::ClassificationFailed, 'Invalid output: ' . $e->getMessage(), $responseModel);
                    }
                }
            }
        }

        $status = is_string($body['status'] ?? null) ? $body['status'] : 'unknown';

        return MapResult::failure($id, $version, ErrorCode::ClassificationFailed, 'No output text (response status: ' . $status . ')', $responseModel);
    }

    /**
     * @param array<array-key, mixed> $error
     */
    private static function errorMessage(array $error): string
    {
        return is_string($error['message'] ?? null) ? $error['message'] : json_encode($error, JSON_THROW_ON_ERROR);
    }
}
