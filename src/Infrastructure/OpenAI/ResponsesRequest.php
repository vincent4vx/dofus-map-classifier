<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\OpenAI;

use function array_map;

/**
 * Build Responses API request bodies
 */
final class ResponsesRequest
{
    /**
     * Request with a text and images as input, and a JSON object following the schema as output (structured output)
     *
     * @param list<string> $imageUrls Images as URL or data URL, sent after the text in this order, with the "high" detail
     * @param array<string, mixed> $schema JSON schema of the output, used in strict mode
     * @param string|null $reasoningEffort Reasoning effort (only for reasoning models), or null to omit it
     *
     * @return array<string, mixed> Body of POST /v1/responses
     */
    public static function structured(
        string $model,
        string $instructions,
        string $text,
        array $imageUrls,
        string $schemaName,
        array $schema,
        ?string $reasoningEffort = null,
    ): array {
        $body = [
            'model' => $model,
            'instructions' => $instructions,
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $text],
                        ...array_map(static fn (string $url) => ['type' => 'input_image', 'image_url' => $url, 'detail' => 'high'], $imageUrls),
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];

        if ($reasoningEffort !== null) {
            $body['reasoning'] = ['effort' => $reasoningEffort];
        }

        return $body;
    }
}
