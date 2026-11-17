<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Service;

use MapClassifier\Map\Model\Rectangle;
use MapClassifier\Sprite\Service\SpriteExampleRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function imagecolorallocate;
use function imagecolorat;
use function imagecreatetruecolor;
use function imagefill;

final class SpriteExampleRendererTest extends TestCase
{
    private const int MAGENTA = 0xFF00FF;

    public function testFramed(): void
    {
        $image = imagecreatetruecolor(100, 80) ?: throw new RuntimeException();
        imagefill($image, 0, 0, imagecolorallocate($image, 0, 128, 0) ?: 0);

        $framed = SpriteExampleRenderer::framed($image, new Rectangle(40, 30, 20, 10));

        $this->assertSame(0x008000, imagecolorat($image, 36, 30), 'Original image unchanged');
        // Frame of 3 pixels, 2 pixels away from the sprite
        foreach ([[35, 35], [37, 35], [62, 35], [64, 35], [50, 25], [50, 27], [50, 42], [50, 44], [35, 25], [64, 44]] as [$x, $y]) {
            $this->assertSame(self::MAGENTA, imagecolorat($framed, $x, $y), 'Frame at ' . $x . ',' . $y);
        }

        foreach ([[34, 35], [38, 35], [39, 35], [60, 35], [61, 35], [65, 35], [50, 24], [50, 28], [50, 41], [50, 45]] as [$x, $y]) {
            $this->assertSame(0x008000, imagecolorat($framed, $x, $y), 'No frame at ' . $x . ',' . $y);
        }

        $this->assertSame(0x008000, imagecolorat($framed, 40, 30), 'Sprite not covered');
        $this->assertSame(0x008000, imagecolorat($framed, 59, 39), 'Sprite not covered');
    }

    public function testFramedOutOfTheImage(): void
    {
        $image = imagecreatetruecolor(100, 80) ?: throw new RuntimeException();
        $framed = SpriteExampleRenderer::framed($image, new Rectangle(-20, 50, 200, 100));

        $this->assertSame(self::MAGENTA, imagecolorat($framed, 0, 60), 'Left kept in the image');
        $this->assertSame(self::MAGENTA, imagecolorat($framed, 99, 60), 'Right kept in the image');
        $this->assertSame(self::MAGENTA, imagecolorat($framed, 50, 79), 'Bottom kept in the image');
        $this->assertSame(self::MAGENTA, imagecolorat($framed, 50, 45), 'Top');
    }
}
