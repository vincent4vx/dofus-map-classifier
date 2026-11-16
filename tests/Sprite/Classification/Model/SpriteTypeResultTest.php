<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Classification\Model;

use MapClassifier\Sprite\Classification\Model\SpriteTypeError;
use MapClassifier\Sprite\Classification\Model\SpriteTypeResult;
use MapClassifier\Sprite\Model\SpriteSet;
use PHPUnit\Framework\TestCase;

use function array_slice;

final class SpriteTypeResultTest extends TestCase
{
    public function testErrorJson(): void
    {
        $result = SpriteTypeResult::failure(SpriteSet::Objects, 12, SpriteTypeError::Refused, 'No', 'luna');
        $data = $result->jsonSerialize();

        $this->assertSame(['set' => 'objects', 'id' => 12, 'status' => 'error', 'error' => 'refused', 'error_message' => 'No'], array_slice($data, 0, 5));

        $loaded = SpriteTypeResult::fromArray($data);
        $this->assertSame(SpriteTypeError::Refused, $loaded->error);
        $this->assertSame('No', $loaded->errorMessage);
        $this->assertFalse($loaded->ok);
    }

    public function testLegacyErrorMessages(): void
    {
        $legacy = static fn (string $error) => SpriteTypeResult::fromArray(['set' => 'objects', 'id' => 1, 'status' => 'error', 'error' => $error]);

        $this->assertSame([SpriteTypeError::Refused, 'No'], [$legacy('Refused: No')->error, $legacy('Refused: No')->errorMessage]);
        $this->assertSame([SpriteTypeError::ImageNotExported, null], [$legacy('Sprite image not exported')->error, $legacy('Sprite image not exported')->errorMessage]);
        $this->assertSame([SpriteTypeError::ClassificationFailed, 'HTTP 500: error'], [$legacy('HTTP 500: error')->error, $legacy('HTTP 500: error')->errorMessage]);
    }

    public function testInvalidStoredType(): void
    {
        $result = SpriteTypeResult::fromArray(['set' => 'objects', 'id' => 1, 'status' => 'ok', 'category' => 'spaceship']);

        $this->assertFalse($result->ok);
        $this->assertSame(SpriteTypeError::ClassificationFailed, $result->error);
        $this->assertSame('Invalid stored type: Invalid category: spaceship', $result->errorMessage);
    }
}
