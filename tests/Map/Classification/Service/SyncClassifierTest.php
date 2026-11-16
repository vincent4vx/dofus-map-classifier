<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Classification\Service;

use MapClassifier\Infrastructure\OpenAI\ConcurrentResponsesClient;
use MapClassifier\Map\Classification\Model\ErrorCode;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Classification\Service\ClassificationPrompt;
use MapClassifier\Map\Classification\Service\ImageEncoder;
use MapClassifier\Map\Classification\Service\MapResultParser;
use MapClassifier\Map\Classification\Service\SyncClassifier;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Tests\BatchFixtures;
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

final class SyncClassifierTest extends TestCase
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
                1, 2 => new JsonMockResponse(json_decode(BatchFixtures::successLine($id, BatchFixtures::classification('desert')), true)['response']['body']),
                3 => new JsonMockResponse(['error' => ['message' => 'Bad request']], ['http_code' => 400]),
                4 => new MockResponse('not json'),
            };
        });

        $received = [];
        $this->classifier($http)->classify($this->maps(), 2, function (MapResult $result) use (&$received) {
            $received[$result->id] = $result;
        });

        $this->assertCount(4, $requests);
        $this->assertSame('POST', $requests[0][0]);
        $this->assertSame('https://api.test/v1/responses', $requests[0][1]);
        $this->assertContains('Authorization: Bearer sk-test', $requests[0][2]);
        $this->assertSame('luna', $requests[0][3]['model']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $requests[0][3]['input'][0]['content'][1]['image_url']);

        $this->assertCount(4, $received);
        $this->assertSame('desert', $this->results->get(1)->classification->biome->value);
        $this->assertSame('v1', $this->results->get(1)->version);
        $this->assertTrue($this->results->get(2)->ok);
        $this->assertSame(ErrorCode::ClassificationFailed, $this->results->get(3)->error);
        $this->assertSame('HTTP 400: Bad request', $this->results->get(3)->errorMessage);
        $this->assertSame(ErrorCode::ClassificationFailed, $this->results->get(4)->error);
    }

    public function testConcurrencyLimit(): void
    {
        $inFlight = 0;
        $max = 0;
        $http = new MockHttpClient(static function () use (&$inFlight, &$max) {
            $max = max($max, ++$inFlight);

            // The body is consumed when stream() reaches this response: the request is not in flight anymore
            return new MockResponse((static function () use (&$inFlight) {
                yield json_encode(json_decode(BatchFixtures::successLine(1, BatchFixtures::classification()), true)['response']['body']);
                --$inFlight;
            })());
        });

        $this->classifier($http)->classify($this->maps(), 2);

        $this->assertSame(2, $max);
        $this->assertCount(4, $this->results->all());
    }

    public function testTransportError(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['error' => 'Connection refused']));

        $this->classifier($http)->classify([$this->maps()[0]], 1);

        $this->assertSame(ErrorCode::ClassificationFailed, $this->results->get(1)->error);
        $this->assertStringContainsString('Connection refused', $this->results->get(1)->errorMessage);
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

    private function classifier(MockHttpClient $http): SyncClassifier
    {
        return new SyncClassifier(
            new ConcurrentResponsesClient($http, static fn () => 'sk-test', 'https://api.test/v1'),
            new ClassificationPrompt('luna'),
            new ImageEncoder(),
            new MapResultParser(),
            $this->results,
            $this->tempDir(),
            'luna',
        );
    }
}
