<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Shore;

use InvalidArgumentException;
use MapClassifier\Sprite\Shore\ShoreRole;
use MapClassifier\Sprite\Shore\ShoreRolePrompt;
use PHPUnit\Framework\TestCase;

final class ShoreRoleTest extends TestCase
{
    public function testReadsTheRoleOfAShorePiece(): void
    {
        $data = ['kind' => 'cliff', 'shape' => 'straight', 'water' => ['s'], 'land' => ['n'], 'continues' => ['e', 'w'], 'description' => 'A straight cliff.'];
        $role = ShoreRole::fromArray($data);

        $this->assertSame('cliff', $role->kind);
        $this->assertSame(['e', 'w'], $role->continues);
        $this->assertSame($data, $role->jsonSerialize());
        $this->assertSame(['kind', 'shape', 'water', 'land', 'continues', 'description'], ShoreRolePrompt::schema()['required']);
    }

    public function testRejectsAnUnknownDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ShoreRole::fromArray(['kind' => 'cliff', 'shape' => 'straight', 'water' => ['up'], 'land' => [], 'continues' => [], 'description' => '']);
    }
}
