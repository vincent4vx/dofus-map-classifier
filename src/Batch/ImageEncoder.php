<?php

declare(strict_types=1);

namespace MapClassifier\Batch;

use InvalidArgumentException;
use RuntimeException;

use function base64_encode;
use function file_get_contents;
use function imagecreatefrompng;
use function imagejpeg;
use function ob_get_clean;
use function ob_start;

/**
 * Encode a rendered PNG map as data URL to send to the model
 * JPEG is used by default to reduce the batch file size (the model resizes images anyway)
 */
final readonly class ImageEncoder
{
    public const string PNG = 'png';
    public const string JPEG = 'jpeg';

    public function __construct(
        private string $format = self::JPEG,
        private int $jpegQuality = 90,
    ) {
        if ($format !== self::PNG && $format !== self::JPEG) {
            throw new InvalidArgumentException('Unsupported image format ' . $format);
        }
    }

    public function dataUrl(string $pngFile): string
    {
        if ($this->format === self::PNG) {
            $data = file_get_contents($pngFile);

            if ($data === false) {
                throw new RuntimeException('Cannot read ' . $pngFile);
            }

            return 'data:image/png;base64,' . base64_encode($data);
        }

        $image = @imagecreatefrompng($pngFile) ?: throw new RuntimeException('Cannot read ' . $pngFile);

        ob_start();
        imagejpeg($image, null, $this->jpegQuality);

        return 'data:image/jpeg;base64,' . base64_encode((string) ob_get_clean());
    }
}
