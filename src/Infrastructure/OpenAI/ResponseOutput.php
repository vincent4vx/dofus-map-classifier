<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\OpenAI;

use function is_array;
use function is_string;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Extract the output of a Responses API response body
 */
final readonly class ResponseOutput
{
    private function __construct(
        /** The output text, if any */
        public ?string $text,
        /** The refusal message, if the model refused to answer */
        public ?string $refusal,
        /** Response status (e.g. "completed", "incomplete") */
        public string $status,
        public ?string $model,
    ) {}

    /**
     * @param array<array-key, mixed> $body
     */
    public static function fromBody(array $body): self
    {
        $text = null;
        $refusal = null;

        foreach (is_array($body['output'] ?? null) ? $body['output'] : [] as $item) {
            if (!is_array($item) || ($item['type'] ?? null) !== 'message' || !is_array($item['content'] ?? null)) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (!is_array($content)) {
                    continue;
                }

                if (($content['type'] ?? null) === 'refusal') {
                    $refusal = (string) ($content['refusal'] ?? '');
                } elseif (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $text ??= $content['text'];
                }
            }
        }

        return new self(
            $text,
            $refusal,
            is_string($body['status'] ?? null) ? $body['status'] : 'unknown',
            is_string($body['model'] ?? null) ? $body['model'] : null,
        );
    }

    /**
     * Get the error message of an error response body
     *
     * @param array<array-key, mixed> $body
     */
    public static function error(int $status, array $body): string
    {
        $error = $body['error'] ?? null;
        $message = match (true) {
            is_array($error) && is_string($error['message'] ?? null) => $error['message'],
            is_array($error) => json_encode($error, JSON_THROW_ON_ERROR),
            default => 'unknown error',
        };

        return 'HTTP ' . ($status ?: '?') . ': ' . $message;
    }
}
