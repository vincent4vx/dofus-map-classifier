<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\Json;

use JsonException;
use RuntimeException;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function rename;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Read and write JSON files
 */
final class JsonFile
{
    /**
     * Encode data to a pretty-printed JSON string
     */
    public static function encode(mixed $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Write data to the file, atomically, creating the parent directory if needed
     *
     * @throws RuntimeException If the directory cannot be created
     */
    public static function write(string $file, mixed $data): void
    {
        $dir = dirname($file);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory ' . $dir);
        }

        file_put_contents($file . '.tmp', self::encode($data) . "\n");
        rename($file . '.tmp', $file);
    }

    /**
     * Read a JSON file containing an object or an array
     *
     * @return array<array-key, mixed>
     *
     * @throws RuntimeException If the file does not exist or is invalid
     */
    public static function read(string $file): array
    {
        if (!is_file($file)) {
            throw new RuntimeException($file . ' not found');
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid ' . $file . ': ' . $e->getMessage(), previous: $e);
        }

        if (!is_array($data)) {
            throw new RuntimeException('Invalid ' . $file . ': an object or an array is expected');
        }

        return $data;
    }
}
