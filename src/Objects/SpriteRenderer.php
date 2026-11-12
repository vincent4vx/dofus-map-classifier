<?php

declare(strict_types=1);

namespace MapClassifier\Objects;

use Arakne\Swf\Extractor\Drawer\Converter\Renderer\ImagickSvgRendererInterface;
use Arakne\Swf\Extractor\Drawer\Converter\Renderer\ImagickSvgRendererResolver;
use Imagick;
use ImagickPixel;
use RuntimeException;

use function max;
use function min;
use function preg_match;
use function preg_replace;
use function round;
use function sprintf;

/**
 * Render a sprite SVG to the PNG sent to the model
 *
 * - transparent margins of the sprite are removed
 * - the sprite is scaled so its largest visible side is around {@see $targetSize} pixels
 *   (small sprites are upscaled without quality loss thanks to vector rendering)
 * - it is drawn on a neutral grey background, so both light and dark sprites are visible
 */
final readonly class SpriteRenderer
{
    public function __construct(
        private int $targetSize = 512,
        private float $maxScale = 16.0,
        private string $backgroundColor = '#a0a0a0',
        private int $padding = 16,
        private ?ImagickSvgRendererInterface $renderer = null,
    ) {}

    public function render(string $svg): RenderedSprite
    {
        if (!preg_match('/<svg[^>]*?\swidth="([\d.]+)(?:px)?"\s+height="([\d.]+)(?:px)?"/', $svg, $matches)) {
            throw new RuntimeException('Cannot read SVG size');
        }

        [$width, $height] = [(float) $matches[1], (float) $matches[2]];

        // First pass to find the visible area, then render again with a scale computed on the visible size
        $scale = $this->scale(max($width, $height));
        $image = $this->rasterize($svg, $width, $height, $scale);

        $betterScale = $this->scale(max($image->getImageWidth(), $image->getImageHeight()) / $scale);

        if ($betterScale > $scale * 1.2) {
            $scale = $betterScale;
            $image = $this->rasterize($svg, $width, $height, $scale);
        }

        $canvas = new Imagick();
        $canvas->newImage(
            $image->getImageWidth() + 2 * $this->padding,
            $image->getImageHeight() + 2 * $this->padding,
            new ImagickPixel($this->backgroundColor),
        );
        $canvas->compositeImage($image, Imagick::COMPOSITE_OVER, $this->padding, $this->padding);
        $canvas->setImageFormat('png');

        return new RenderedSprite(
            $canvas->getImageBlob(),
            round($image->getImageWidth() / $scale, 1),
            round($image->getImageHeight() / $scale, 1),
        );
    }

    private function scale(float $size): float
    {
        return min($this->maxScale, max(1.0, $this->targetSize / max($size, 1.0)));
    }

    /**
     * Render the SVG with a transparent background, and trim transparent margins
     */
    private function rasterize(string $svg, float $width, float $height, float $scale): Imagick
    {
        // Add a viewBox to scale the drawing with the new size
        $svg = (string) preg_replace(
            '/<svg([^>]*?)\swidth="[^"]*"\s+height="[^"]*"/',
            sprintf('<svg$1 viewBox="0 0 %F %F" width="%dpx" height="%dpx"', $width, $height, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))),
            $svg,
            1,
        );

        $image = ($this->renderer ?? ImagickSvgRendererResolver::get())->open($svg, 'transparent');

        // Trim works on the corner color: only trim when the margin is transparent,
        // otherwise an opaque sprite (e.g. a background) would be fully removed
        if ($image->getImagePixelColor(0, 0)->getColorValue(Imagick::COLOR_ALPHA) < 0.01) {
            $image->trimImage(0);
            $image->setImagePage(0, 0, 0, 0);
        }

        return $image;
    }
}
