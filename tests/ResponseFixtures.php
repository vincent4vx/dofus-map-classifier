<?php

declare(strict_types=1);

namespace MapClassifier\Tests;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Build OpenAI Responses API bodies
 */
final class ResponseFixtures
{
    /**
     * Successful response body, with the classification as output text
     *
     * @param array<string, mixed> $classification
     *
     * @return array<string, mixed>
     */
    public static function success(array $classification): array
    {
        return [
            'id' => 'resp_1',
            'object' => 'response',
            'status' => 'completed',
            'model' => 'luna-2026-01-01',
            'output' => [
                ['type' => 'reasoning', 'summary' => []],
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'output_text', 'text' => json_encode($classification, JSON_THROW_ON_ERROR), 'annotations' => []],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function classification(string $biome = 'forest'): array
    {
        return [
            'biome' => $biome,
            'biome_other' => null,
            'indoor' => false,
            'location_type' => 'wild',
            'tags' => ['trees', 'river'],
            'description' => 'A forest crossed by a river.',
        ];
    }
}
