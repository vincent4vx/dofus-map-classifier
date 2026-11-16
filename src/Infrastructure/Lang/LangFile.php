<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\Lang;

use RuntimeException;

use function basename;
use function glob;
use function preg_match;
use function preg_quote;

/**
 * Locate lang files "{name}_{lang}_{version}.swf"
 */
final class LangFile
{
    /**
     * Path of the lang file with the highest version
     *
     * @throws RuntimeException If no lang file is found
     */
    public static function latest(string $langDir, string $name, string $lang): string
    {
        $best = null;
        $bestVersion = -1;

        foreach (glob($langDir . '/' . $name . '_' . $lang . '_*.swf') ?: [] as $file) {
            if (preg_match('/^' . preg_quote($name . '_' . $lang, '/') . '_(\d+)\.swf$/', basename($file), $matches) && (int) $matches[1] > $bestVersion) {
                $best = $file;
                $bestVersion = (int) $matches[1];
            }
        }

        return $best ?? throw new RuntimeException('No ' . $name . '_' . $lang . '_*.swf lang file found in ' . $langDir);
    }
}
