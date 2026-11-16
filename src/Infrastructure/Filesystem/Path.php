<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\Filesystem;

use function str_starts_with;
use function strlen;
use function substr;

/**
 * Paths helpers
 */
final class Path
{
    /**
     * Path of the target relative to the directory, to reference it from an HTML file of this directory
     * Fallback to an absolute "file://" URL if the target is not inside the directory
     */
    public static function relativeUrl(string $fromDir, string $target): string
    {
        if (str_starts_with($target, $fromDir . '/')) {
            return substr($target, strlen($fromDir) + 1);
        }

        return 'file://' . $target;
    }
}
