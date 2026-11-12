<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Objects;

use MapClassifier\Objects\SpriteRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function getimagesizefromstring;
use function imagecolorat;
use function imagecreatefromstring;
use function imagepalettetotruecolor;

final class SpriteRendererTest extends TestCase
{
    public function testRenderTrimsAndScales(): void
    {
        // 100x50 sprite with a 20x10 visible red rectangle
        $sprite = (new SpriteRenderer(targetSize: 200, padding: 10))->render(<<<'SVG'
            <?xml version="1.0"?>
            <svg xmlns="http://www.w3.org/2000/svg" width="100px" height="50px"><rect x="70" y="30" width="20" height="10" fill="#ff0000"/></svg>
            SVG);

        $this->assertEqualsWithDelta(20, $sprite->width, 0.5);
        $this->assertEqualsWithDelta(10, $sprite->height, 0.5);

        // Visible part scaled to 200px, with padding
        [$width, $height] = getimagesizefromstring($sprite->png);
        $this->assertEqualsWithDelta(220, $width, 3);
        $this->assertEqualsWithDelta(120, $height, 3);

        $gd = imagecreatefromstring($sprite->png);
        imagepalettetotruecolor($gd);
        $this->assertSame(0xA0A0A0, imagecolorat($gd, 2, 2) & 0xFFFFFF, 'grey background');
        $this->assertSame(0xFF0000, imagecolorat($gd, (int) ($width / 2), (int) ($height / 2)) & 0xFFFFFF, 'scaled sprite');
    }

    public function testOpaqueSpriteIsNotTrimmed(): void
    {
        $sprite = (new SpriteRenderer(targetSize: 512, maxScale: 10.0, padding: 0))->render(
            '<svg xmlns="http://www.w3.org/2000/svg" width="4px" height="2px"><rect width="4" height="2" fill="#00ff00"/></svg>',
        );

        [$width, $height] = getimagesizefromstring($sprite->png);

        $this->assertEqualsWithDelta(40, $width, 1);
        $this->assertEqualsWithDelta(20, $height, 1);
        $this->assertEqualsWithDelta(4, $sprite->width, 0.1);
    }

    public function testInvalidSvg(): void
    {
        $this->expectException(RuntimeException::class);
        (new SpriteRenderer())->render('<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    }
}
