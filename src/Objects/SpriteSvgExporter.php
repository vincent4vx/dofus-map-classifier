<?php

declare(strict_types=1);

namespace MapClassifier\Objects;

use Arakne\Swf\Extractor\Drawer\Converter\Converter;
use Arakne\Swf\Extractor\DrawableInterface;
use Arakne\Swf\Extractor\SwfExtractor;
use Arakne\Swf\SwfFile;
use RuntimeException;
use Throwable;

use function file_put_contents;
use function glob;
use function is_dir;
use function is_file;
use function is_numeric;
use function mkdir;
use function rename;

/**
 * Export ground and object sprites of the client to SVG files: "{dir}/{set}/{id}.svg"
 */
final readonly class SpriteSvgExporter
{
    public function __construct(
        private string $dofusPath,
        private Converter $converter = new Converter(subpixelStrokeWidth: false),
    ) {}

    /**
     * Export the sprites of a set
     *
     * @param array<int, mixed>|null $only Only export these sprite ids (keys), or null to export all sprites
     * @param callable(int $id, bool $exported): void|null $onSprite Called after each sprite
     *
     * @return array{exported: int, skipped: int, failed: array<int, string>}
     */
    public function export(SpriteSet $set, string $dir, ?array $only = null, bool $force = false, ?callable $onSprite = null): array
    {
        $target = $dir . '/' . $set->value;

        if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
            throw new RuntimeException('Cannot create directory ' . $target);
        }

        $report = ['exported' => 0, 'skipped' => 0, 'failed' => []];
        $pattern = $set === SpriteSet::Grounds ? 'g*.swf' : 'o*.swf';

        foreach (glob($this->dofusPath . '/clips/gfx/' . $pattern) ?: [] as $file) {
            $extractor = new SwfExtractor(new SwfFile($file));

            try {
                foreach ($extractor->exported() as $name => $_) {
                    if (!is_numeric($name)) {
                        continue;
                    }

                    $id = (int) $name;

                    if ($only !== null && !isset($only[$id])) {
                        continue;
                    }

                    $svgFile = $target . '/' . $id . '.svg';

                    if (!$force && is_file($svgFile)) {
                        ++$report['skipped'];

                        if ($onSprite) {
                            $onSprite($id, true);
                        }

                        continue;
                    }

                    try {
                        $character = $extractor->byName((string) $name);

                        if (!$character instanceof DrawableInterface) {
                            throw new RuntimeException('Not a drawable character');
                        }

                        file_put_contents($svgFile . '.tmp', $this->converter->toSvg($character));
                        rename($svgFile . '.tmp', $svgFile);
                        ++$report['exported'];
                    } catch (Throwable $e) {
                        $report['failed'][$id] = $e->getMessage();
                    }

                    if ($onSprite) {
                        $onSprite($id, !isset($report['failed'][$id]));
                    }

                    $extractor->releaseIfOutOfMemory();
                }
            } finally {
                $extractor->release();
            }
        }

        return $report;
    }
}
