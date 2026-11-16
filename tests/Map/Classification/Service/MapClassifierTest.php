<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Classification\Service;

use LogicException;
use MapClassifier\Infrastructure\OpenAI\ConcurrentResponsesClient;
use MapClassifier\Map\Classification\Model\ErrorCode;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Classification\Service\ClassificationPrompt;
use MapClassifier\Map\Classification\Service\MapClassifier;
use MapClassifier\Map\Classification\Service\MapResultParser;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Tests\ResponseFixtures;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

use function imagecreatetruecolor;
use function imagepng;
use function json_decode;
use function json_encode;
use function max;
use function substr;

final class MapClassifierTest extends TestCase
{
    use TempDirTrait;

    private MapResultRepository $results;

    protected function setUp(): void
    {
        $this->results = new MapResultRepository($this->tempDir() . '/results');

        foreach ([1, 2, 3, 4] as $id) {
            imagepng(imagecreatetruecolor(742, 432), $this->tempDir() . '/' . $id . '.png');
        }
    }

    public function testClassify(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests) {
            $body = json_decode($options['body'], true);
            $requests[] = [$method, $url, $options['headers'], $body];
            $id = (int) substr($body['input'][0]['content'][0]['text'], 17, -1);

            return match ($id) {
                1, 2 => new JsonMockResponse(ResponseFixtures::success(ResponseFixtures::classification('desert'))),
                3 => new JsonMockResponse(['error' => ['message' => 'Bad request']], ['http_code' => 400]),
                4 => new MockResponse('not json'),
                default => throw new LogicException('Unexpected map ' . $id),
            };
        });

        $received = [];
        $this->classifier($http)->classify($this->maps(), concurrency: 2, onResult: function (MapResult $result) use (&$received) {
            $received[$result->id] = $result;
        });

        $this->assertCount(4, $requests);
        $this->assertSame('POST', $requests[0][0]);
        $this->assertSame('https://api.test/v1/responses', $requests[0][1]);
        $this->assertContains('Authorization: Bearer sk-test', $requests[0][2]);
        $this->assertSame('luna', $requests[0][3]['model']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $requests[0][3]['input'][0]['content'][1]['image_url']);

        $this->assertCount(4, $received);
        $this->assertSame('desert', $this->mapResult(1)->classification?->biome?->value);
        $this->assertSame('v1', $this->mapResult(1)->version);
        $this->assertTrue($this->mapResult(2)->ok);
        $this->assertSame(ErrorCode::ClassificationFailed, $this->mapResult(3)->error);
        $this->assertSame('HTTP 400: Bad request', $this->mapResult(3)->errorMessage);
        $this->assertSame(ErrorCode::ClassificationFailed, $this->mapResult(4)->error);
    }

    public function testConcurrencyLimit(): void
    {
        $inFlight = 0;
        /** @var int $max Modified by the HTTP client callback */
        $max = 0;
        $http = new MockHttpClient(static function () use (&$inFlight, &$max) {
            $max = max($max, ++$inFlight);

            // The body is consumed when stream() reaches this response: the request is not in flight anymore
            return new MockResponse((static function () use (&$inFlight) {
                yield (string) json_encode(ResponseFixtures::success(ResponseFixtures::classification()));
                --$inFlight;
            })());
        });

        $this->classifier($http)->classify($this->maps(), concurrency: 2);

        $this->assertSame(2, $max);
        $this->assertCount(4, $this->results->all());
    }

    public function testTransportError(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['error' => 'Connection refused']));

        $this->classifier($http)->classify([$this->maps()[0]], concurrency: 1);

        $this->assertSame(ErrorCode::ClassificationFailed, $this->mapResult(1)->error);
        $this->assertStringContainsString('Connection refused', (string) $this->mapResult(1)->errorMessage);
    }

    private function mapResult(int $id): MapResult
    {
        $result = $this->results->get($id);
        $this->assertNotNull($result);

        return $result;
    }

    /**
     * @return list<MapFile>
     */
    private function maps(): array
    {
        return [
            new MapFile(1, 'v1', true, ''),
            new MapFile(2, 'v2', true, ''),
            new MapFile(3, 'v3', true, ''),
            new MapFile(4, 'v4', true, ''),
        ];
    }

    private function classifier(MockHttpClient $http): MapClassifier
    {
        return new MapClassifier(
            new ConcurrentResponsesClient($http, static fn () => 'sk-test', 'https://api.test/v1'),
            new ClassificationPrompt('luna'),
            new MapResultParser(),
            $this->results,
            $this->tempDir(),
            'luna',
        );
    }
}
