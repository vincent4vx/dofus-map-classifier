<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Infrastructure\OpenAI;

use Closure;
use MapClassifier\Infrastructure\OpenAI\OpenAIClient;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

use function file_get_contents;
use function file_put_contents;
use function json_decode;

final class OpenAIClientTest extends TestCase
{
    use TempDirTrait;

    public function testUploadBatchFile(): void
    {
        file_put_contents($this->tempDir() . '/in.jsonl', '{"custom_id":"map-1"}');
        $request = [];

        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$request) {
            $body = $options['body'];

            if ($body instanceof Closure) {
                $content = '';

                while (($chunk = $body(8192)) !== '') {
                    $content .= $chunk;
                }

                $body = $content;
            }

            $request = [$method, $url, $options['headers'], $body];

            return new JsonMockResponse(['id' => 'file-abc']);
        });

        $client = new OpenAIClient($http, 'sk-test', 'https://api.test/v1');

        $this->assertSame('file-abc', $client->uploadBatchFile($this->tempDir() . '/in.jsonl'));

        [$method, $url, $headers, $body] = $request;
        $this->assertSame('POST', $method);
        $this->assertSame('https://api.test/v1/files', $url);
        $this->assertContains('Authorization: Bearer sk-test', $headers);
        $this->assertStringContainsString('name="purpose"', $body);
        $this->assertStringContainsString("\r\n\r\nbatch\r\n", $body);
        $this->assertStringContainsString('filename="in.jsonl"', $body);
        $this->assertStringContainsString('{"custom_id":"map-1"}', $body);
    }

    public function testCreateAndGetBatch(): void
    {
        $create = new JsonMockResponse(['id' => 'batch_1', 'status' => 'validating', 'request_counts' => ['total' => 0, 'completed' => 0, 'failed' => 0]]);
        $get = new JsonMockResponse([
            'id' => 'batch_1',
            'status' => 'completed',
            'output_file_id' => 'file-out',
            'error_file_id' => null,
            'request_counts' => ['total' => 10, 'completed' => 9, 'failed' => 1],
        ]);
        $client = new OpenAIClient(new MockHttpClient([$create, $get]), 'sk-test');

        $info = $client->createBatch('file-abc', '/v1/responses', ['project' => 'test']);
        $this->assertSame('batch_1', $info->id);
        $this->assertFalse($info->terminal);
        $this->assertSame('https://api.openai.com/v1/batches', $create->getRequestUrl());
        $this->assertSame([
            'input_file_id' => 'file-abc',
            'endpoint' => '/v1/responses',
            'completion_window' => '24h',
            'metadata' => ['project' => 'test'],
        ], json_decode($create->getRequestOptions()['body'], true));

        $info = $client->getBatch('batch_1');
        $this->assertTrue($info->terminal);
        $this->assertSame('file-out', $info->outputFileId);
        $this->assertNull($info->errorFileId);
        $this->assertSame([10, 9, 1], [$info->total, $info->completed, $info->failed]);
        $this->assertSame('https://api.openai.com/v1/batches/batch_1', $get->getRequestUrl());
    }

    public function testBatchErrors(): void
    {
        $client = new OpenAIClient(new MockHttpClient(new JsonMockResponse([
            'id' => 'batch_1',
            'status' => 'failed',
            'errors' => ['data' => [['code' => 'invalid', 'message' => 'Bad model', 'line' => 1]]],
        ])), 'sk-test');

        $this->assertSame('line 1: Bad model', $client->getBatch('batch_1')->errors);
    }

    public function testDownloadFile(): void
    {
        $response = new MockResponse("line1\nline2\n");
        $client = new OpenAIClient(new MockHttpClient($response), 'sk-test');

        $client->downloadFile('file-out', $this->tempDir() . '/out.jsonl');

        $this->assertSame("line1\nline2\n", file_get_contents($this->tempDir() . '/out.jsonl'));
        $this->assertSame('https://api.openai.com/v1/files/file-out/content', $response->getRequestUrl());
    }

    public function testApiError(): void
    {
        $client = new OpenAIClient(new MockHttpClient(new JsonMockResponse(['error' => ['message' => 'Invalid key']], ['http_code' => 401])), 'sk-test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API error (HTTP 401)');

        $client->getBatch('batch_1');
    }
}
