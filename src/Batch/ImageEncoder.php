<?php

declare(strict_types=1);

namespace MapClassifier\Batch;

use InvalidArgumentException;
use RuntimeException;

use function base64_encode;
use function file_get_contents;
use function imagecreatefrompng;
use function imagejpeg;
use function imagepng;
use function imagescale;
use function imagesx;
use function imagesy;
use function round;

use const IMG_BICUBIC;
use function ob_get_clean;
use function ob_start;

/**
 * Encode a rendered PNG map as data URL to send to the model
 * JPEG is used by default to reduce the batch file size
 *
 * The image is upscaled (x2 by default): with the native size, the model has not enough details
 * and confuses some textures (e.g. pale grass patches seen as water)
 */
final readonly class ImageEncoder
{
    public const string PNG = 'png';
    public const string JPEG = 'jpeg';

    public const float DEFAULT_SCALE = 2.0;

    public function __construct(
        private string $format = self::JPEG,
        private float $scale = self::DEFAULT_SCALE,
        private int $jpegQuality = 90,
    ) {
        if ($format !== self::PNG && $format !== self::JPEG) {
            throw new InvalidArgumentException('Unsupported image format ' . $format);
        }

        if ($scale <= 0 || $scale > 4) {
            throw new InvalidArgumentException('Image scale must be in ]0, 4]');
        }
    }

    public function dataUrl(string $pngFile): string
    {
        if ($this->format === self::PNG && $this->scale === 1.0) {
            $data = file_get_contents($pngFile);

            if ($data === false) {
                throw new RuntimeException('Cannot read ' . $pngFile);
            }

            return 'data:image/png;base64,' . base64_encode($data);
        }

        $image = @imagecreatefrompng($pngFile) ?: throw new RuntimeException('Cannot read ' . $pngFile);

        if ($this->scale !== 1.0) {
            $image = imagescale($image, (int) round(imagesx($image) * $this->scale), (int) round(imagesy($image) * $this->scale), IMG_BICUBIC)
                ?: throw new RuntimeException('Cannot scale ' . $pngFile);
        }

        ob_start();
        $this->format === self::PNG ? imagepng($image) : imagejpeg($image, null, $this->jpegQuality);

        return 'data:image/' . $this->format . ';base64,' . base64_encode((string) ob_get_clean());
    }
}
