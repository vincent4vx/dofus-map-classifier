<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Classification\Service;

use LogicException;
use MapClassifier\Infrastructure\OpenAI\ConcurrentResponsesClient;
use MapClassifier\Sprite\Classification\Model\ObjectCategory;
use MapClassifier\Sprite\Classification\Model\ObjectTypeResult;
use MapClassifier\Sprite\Classification\Repository\ObjectTypeRepository;
use MapClassifier\Sprite\Classification\Service\ObjectTypeClassifier;
use MapClassifier\Sprite\Classification\Service\ObjectTypePrompt;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\ObjectProfile;
use MapClassifier\Sprite\Model\RenderedSprite;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Repository\SpriteImageRepository;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

use function base64_encode;
use function json_decode;
use function json_encode;
use function preg_match;

final class ObjectTypeClassifierTest extends TestCase
{
    use TempDirTrait;

    public function testClassify(): void
    {
        $sprites = new SpriteImageRepository($this->tempDir() . '/sprites');

        foreach ([1, 2, 3, 4] as $id) {
            $sprites->save(SpriteSet::Objects, $id, new RenderedSprite('png-' . $id, 10.0, 10.0));
        }

        $sprites->flush(SpriteSet::Objects);

        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests) {
            $body = json_decode($options['body'], true);
            $id = preg_match('/sprite o(\d+)\./', $body['input'][0]['content'][0]['text'], $m) ? (int) $m[1] : 0;
            $requests[$id] = $body;

            return match ($id) {
                1 => new JsonMockResponse(self::response(['category' => 'tree', 'name' => 'Pine Tree', 'keywords' => ['tree', 'pine'], 'description' => 'A pine.'])),
                2 => new JsonMockResponse(['error' => ['message' => 'Bad request']], ['http_code' => 400]),
                3 => new JsonMockResponse(self::response(['category' => 'spaceship', 'name' => 'x', 'keywords' => [], 'description' => ''])),
                4 => new JsonMockResponse(['model' => 'luna', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'No']]]]]),
                default => throw new LogicException('Unexpected sprite ' . $id),
            };
        });

        $storage = new ObjectTypeRepository($this->tempDir() . '/types');
        $classifier = new ObjectTypeClassifier(
            new ConcurrentResponsesClient($http, static fn () => 'sk-test', 'https://api.test/v1'),
            new ObjectTypePrompt('luna'),
            $sprites,
            $storage,
            'luna',
        );

        $received = [];
        $classifier->classify([$this->profile(1), $this->profile(2), $this->profile(3), $this->profile(4), $this->profile(5)], 2, function (ObjectTypeResult $result) use (&$received) {
            $received[] = $result->id;
        });

        $this->assertCount(5, $received);
        $this->assertCount(4, $requests);
        $this->assertStringContainsString('Visible size: 10 x 10 game pixels', $requests[1]['input'][0]['content'][0]['text']);
        $this->assertSame('data:image/png;base64,' . base64_encode('png-1'), $requests[1]['input'][0]['content'][1]['image_url']);

        $tree = $storage->get(SpriteSet::Objects, 1);
        $this->assertNotNull($tree);
        $this->assertTrue($tree->ok);
        $this->assertNotNull($tree->classification);
        $this->assertSame(ObjectCategory::Tree, $tree->classification->category);
        $this->assertSame('pine tree', $tree->classification->name);
        $this->assertSame('luna-2026', $tree->model);

        $this->assertSame('HTTP 400: Bad request', $storage->get(SpriteSet::Objects, 2)?->error);
        $this->assertSame('Invalid output: Invalid category: spaceship', $storage->get(SpriteSet::Objects, 3)?->error);
        $this->assertSame('Refused: No', $storage->get(SpriteSet::Objects, 4)?->error);
        $this->assertSame('Sprite image not exported', $storage->get(SpriteSet::Objects, 5)?->error);
        $this->assertSame([1, 2, 3, 4, 5], array_keys($storage->all(SpriteSet::Objects)));
        $this->assertSame([], $storage->all(SpriteSet::Grounds));
    }

    private function profile(int $id): ObjectProfile
    {
        return new ObjectProfile(SpriteSet::Objects, $id, Layer::Layer2, 1, 1, 0, [], [], [], [], []);
    }

    /**
     * @param array<string, mixed> $output
     * @return array<string, mixed>
     */
    private static function response(array $output): array
    {
        return [
            'model' => 'luna-2026',
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($output)]]]],
        ];
    }
}
