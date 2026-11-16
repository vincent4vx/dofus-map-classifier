<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Infrastructure\OpenAI;

use MapClassifier\Infrastructure\OpenAI\ResponseFailure;
use MapClassifier\Infrastructure\OpenAI\StructuredResponse;
use MapClassifier\Tests\ResponseFixtures;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StructuredResponseTest extends TestCase
{
    public function testSuccess(): void
    {
        $response = StructuredResponse::parse(200, ResponseFixtures::success(['biome' => 'snow']), null, 'luna');

        $this->assertSame(['biome' => 'snow'], $response->output);
        $this->assertNull($response->failure);
        $this->assertNull($response->message);
        $this->assertSame('luna-2026-01-01', $response->model, 'Model of the response');
    }

    public function testFailures(): void
    {
        $failure = static function (int $status, array $body, ?RuntimeException $error = null): array {
            $response = StructuredResponse::parse($status, $body, $error, 'luna');

            return [$response->failure, $response->message, $response->model, $response->output];
        };

        $this->assertSame([ResponseFailure::RequestFailed, 'Connection refused', 'luna', null], $failure(0, [], new RuntimeException('Connection refused')));
        $this->assertSame([ResponseFailure::RequestFailed, 'HTTP 400: Invalid image', 'luna', null], $failure(400, ['error' => ['message' => 'Invalid image']]));
        $this->assertSame([ResponseFailure::RequestFailed, 'HTTP 500: {"code":"server_error"}', 'luna', null], $failure(500, ['error' => ['code' => 'server_error']]));
        $this->assertSame([ResponseFailure::RequestFailed, 'HTTP ?: unknown error', 'luna', null], $failure(0, []));
        $this->assertSame([ResponseFailure::Refused, 'No', 'luna-2', null], $failure(200, [
            'model' => 'luna-2',
            'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'No']]]],
        ]));
        $this->assertSame([ResponseFailure::NoOutput, 'No output text (response status: incomplete)', 'luna', null], $failure(200, ['status' => 'incomplete', 'output' => []]));
        $this->assertSame([ResponseFailure::InvalidOutput, 'Invalid output: Syntax error', 'luna', null], $failure(200, [
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'not json']]]],
        ]));
    }
}
