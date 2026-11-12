<?php

declare(strict_types=1);

namespace MapClassifier\Tests;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Build batch output lines like the OpenAI API
 */
final class BatchFixtures
{
    /**
     * @param array<string, mixed> $classification
     */
    public static function successLine(int $mapId, array $classification): string
    {
        return self::responseLine($mapId, 200, [
            'id' => 'resp_' . $mapId,
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
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function responseLine(int $mapId, int $status, array $body): string
    {
        return json_encode([
            'id' => 'batch_req_' . $mapId,
            'custom_id' => 'map-' . $mapId,
            'response' => ['status_code' => $status, 'request_id' => 'req_' . $mapId, 'body' => $body],
            'error' => null,
        ], JSON_THROW_ON_ERROR);
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
