<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Classification\Repository;

use MapClassifier\Map\Classification\Model\ErrorCode;
use MapClassifier\Map\Classification\Model\MapClassification;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Classification\Repository\ResultRenderErrorRepository;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Model\RenderError;
use MapClassifier\Tests\ResponseFixtures;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

final class ResultRenderErrorRepositoryTest extends TestCase
{
    use TempDirTrait;

    public function testSaveGetDelete(): void
    {
        $results = new MapResultRepository($this->tempDir());
        $errors = new ResultRenderErrorRepository($results);
        $map = new MapFile(10, 'v1', true, '');

        $this->assertNull($errors->get(10));

        $errors->save($map, new RenderException(RenderError::MissingKey, 'No key'));
        $this->assertSame(RenderError::MissingKey, $errors->get(10));

        $result = $results->get(10);
        $this->assertNotNull($result);
        $this->assertSame(ErrorCode::MissingKey, $result->error);
        $this->assertSame('No key', $result->errorMessage);
        $this->assertSame('v1', $result->version);

        $errors->delete(10);
        $this->assertNull($results->get(10));
    }

    public function testOtherResultsAreNotRenderErrors(): void
    {
        $results = new MapResultRepository($this->tempDir());
        $errors = new ResultRenderErrorRepository($results);

        $results->save(MapResult::success(1, 'v', MapClassification::fromArray(ResponseFixtures::classification()), 'luna'));
        $results->save(MapResult::failure(2, 'v', ErrorCode::ClassificationFailed, 'HTTP 500'));

        $this->assertNull($errors->get(1));
        $this->assertNull($errors->get(2));

        $errors->delete(1);
        $errors->delete(2);
        $this->assertNotNull($results->get(1), 'Only render errors are deleted');
        $this->assertNotNull($results->get(2), 'Only render errors are deleted');
    }
}
