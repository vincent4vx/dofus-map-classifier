<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Classification\Service;

use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\ErrorCode;
use MapClassifier\Map\Classification\Service\MapResultParser;
use MapClassifier\Tests\ResponseFixtures;
use PHPUnit\Framework\TestCase;

final class MapResultParserTest extends TestCase
{
    private MapResultParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MapResultParser();
    }

    public function testSuccess(): void
    {
        $result = $this->parser->parse(42, 'v1', 200, ResponseFixtures::success(ResponseFixtures::classification('swamp')), 'luna');

        $this->assertTrue($result->ok);
        $this->assertNotNull($result->classification);
        $this->assertSame(42, $result->id);
        $this->assertSame('v1', $result->version);
        $this->assertSame('luna-2026-01-01', $result->model);
        $this->assertSame(Biome::Swamp, $result->classification->biome);
        $this->assertSame(['trees', 'river'], $result->classification->tags);
    }

    public function testHttpError(): void
    {
        $result = $this->parser->parse(42, 'v1', 400, ['error' => ['message' => 'Invalid image']], 'luna');

        $this->assertSame(ErrorCode::ClassificationFailed, $result->error);
        $this->assertSame('HTTP 400: Invalid image', $result->errorMessage);
        $this->assertSame('luna', $result->model);
        $this->assertSame('HTTP ?: unknown error', $this->parser->parse(42, 'v1', 0, [], 'luna')->errorMessage);
    }

    public function testRefusal(): void
    {
        $result = $this->parser->parse(42, 'v1', 200, [
            'model' => 'luna',
            'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'No']]]],
        ], 'luna');

        $this->assertSame(ErrorCode::Refused, $result->error);
        $this->assertSame('No', $result->errorMessage);
    }

    public function testInvalidOutput(): void
    {
        $result = $this->parser->parse(42, 'v1', 200, ResponseFixtures::success(['biome' => 'ocean']), 'luna');

        $this->assertSame(ErrorCode::ClassificationFailed, $result->error);
        $this->assertSame('Invalid output: Invalid biome: ocean', $result->errorMessage);
    }

    public function testIncompleteResponse(): void
    {
        $result = $this->parser->parse(42, 'v1', 200, ['status' => 'incomplete', 'output' => []], 'luna');

        $this->assertSame(ErrorCode::ClassificationFailed, $result->error);
        $this->assertSame('No output text (response status: incomplete)', $result->errorMessage);
    }
}
