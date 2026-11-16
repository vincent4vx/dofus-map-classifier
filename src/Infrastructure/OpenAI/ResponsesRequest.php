<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\OpenAI;

/**
 * Build Responses API request bodies
 */
final class ResponsesRequest
{
    /**
     * Request with a text and an image as input, and a JSON object following the schema as output (structured output)
     *
     * @param string $imageUrl Image as URL or data URL, sent with the "high" detail
     * @param array<string, mixed> $schema JSON schema of the output, used in strict mode
     * @param string|null $reasoningEffort Reasoning effort (only for reasoning models), or null to omit it
     *
     * @return array<string, mixed> Body of POST /v1/responses
     */
    public static function structured(
        string $model,
        string $instructions,
        string $text,
        string $imageUrl,
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
                        ['type' => 'input_image', 'image_url' => $imageUrl, 'detail' => 'high'],
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
