<?php

declare(strict_types=1);

namespace MapClassifier\OpenAI;

use function is_array;
use function is_string;

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
        $message = is_array($body['error'] ?? null) && is_string($body['error']['message'] ?? null)
            ? $body['error']['message']
            : 'unknown error';

        return 'HTTP ' . ($status ?: '?') . ': ' . $message;
    }
}
