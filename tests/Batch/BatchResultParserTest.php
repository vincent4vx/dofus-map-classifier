<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Batch;

use MapClassifier\Batch\BatchResultParser;
use MapClassifier\Classification\Biome;
use MapClassifier\Storage\ErrorCode;
use MapClassifier\Tests\BatchFixtures;
use PHPUnit\Framework\TestCase;

use function json_encode;

final class BatchResultParserTest extends TestCase
{
    private BatchResultParser $parser;

    protected function setUp(): void
    {
        $this->parser = new BatchResultParser();
    }

    public function testSuccess(): void
    {
        $result = $this->parser->parse(BatchFixtures::successLine(42, BatchFixtures::classification('swamp')), 'luna', [42 => 'v1']);

        $this->assertTrue($result->ok);
        $this->assertSame(42, $result->id);
        $this->assertSame('v1', $result->version);
        $this->assertSame('luna-2026-01-01', $result->model);
        $this->assertSame(Biome::Swamp, $result->classification->biome);
        $this->assertSame(['trees', 'river'], $result->classification->tags);
    }

    public function testInvalidLines(): void
    {
        $this->assertNull($this->parser->parse('not json', 'luna'));
        $this->assertNull($this->parser->parse('{"foo": 1}', 'luna'));
        $this->assertNull($this->parser->parse('{"custom_id": "other-1"}', 'luna'));
    }

    public function testRequestError(): void
    {
        $line = json_encode(['custom_id' => 'map-42', 'response' => null, 'error' => ['code' => 'batch_expired', 'message' => 'Expired']]);
        $result = $this->parser->parse($line, 'luna');

        $this->assertFalse($result->ok);
        $this->assertSame(ErrorCode::ClassificationFailed, $result->error);
        $this->assertSame('Expired', $result->errorMessage);
    }

    public function testHttpError(): void
    {
        $result = $this->parser->parse(BatchFixtures::responseLine(42, 400, ['error' => ['message' => 'Invalid image']]), 'luna');

        $this->assertSame(ErrorCode::ClassificationFailed, $result->error);
        $this->assertSame('HTTP 400: Invalid image', $result->errorMessage);
        $this->assertSame('luna', $result->model);
    }

    public function testRefusal(): void
    {
        $result = $this->parser->parse(BatchFixtures::responseLine(42, 200, [
            'model' => 'luna',
            'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'No']]]],
        ]), 'luna');

        $this->assertSame(ErrorCode::Refused, $result->error);
        $this->assertSame('No', $result->errorMessage);
    }

    public function testInvalidOutput(): void
    {
        $result = $this->parser->parse(BatchFixtures::successLine(42, ['biome' => 'ocean']), 'luna');

        $this->assertSame(ErrorCode::ClassificationFailed, $result->error);
        $this->assertSame('Invalid output: Invalid biome: ocean', $result->errorMessage);
    }

    public function testIncompleteResponse(): void
    {
        $result = $this->parser->parse(BatchFixtures::responseLine(42, 200, ['status' => 'incomplete', 'output' => []]), 'luna');

        $this->assertSame(ErrorCode::ClassificationFailed, $result->error);
        $this->assertSame('No output text (response status: incomplete)', $result->errorMessage);
    }
}
