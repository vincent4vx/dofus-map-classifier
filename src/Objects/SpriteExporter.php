<?php

declare(strict_types=1);

namespace MapClassifier\Objects;

use Arakne\Swf\Extractor\Drawer\Converter\Converter;
use Arakne\Swf\Extractor\DrawableInterface;
use Arakne\Swf\Extractor\SwfExtractor;
use Arakne\Swf\SwfFile;
use RuntimeException;
use Throwable;

use function glob;
use function is_numeric;

/**
 * Export ground and object sprites of the client to the PNG files sent to the model
 * Sprites are extracted as SVG with ArakneSwf, then rendered with {@see SpriteRenderer}
 */
final readonly class SpriteExporter
{
    public function __construct(
        private string $dofusPath,
        private SpriteFiles $files,
        private SpriteRenderer $renderer = new SpriteRenderer(),
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
    public function export(SpriteSet $set, ?array $only = null, bool $force = false, ?callable $onSprite = null): array
    {
        $report = ['exported' => 0, 'skipped' => 0, 'failed' => []];
        $pattern = $set === SpriteSet::Grounds ? 'g*.swf' : 'o*.swf';
        $done = [];

        try {
            foreach (glob($this->dofusPath . '/clips/gfx/' . $pattern) ?: [] as $file) {
                $extractor = new SwfExtractor(new SwfFile($file));

                try {
                    foreach ($extractor->exported() as $name => $_) {
                        if (!is_numeric($name)) {
                            continue;
                        }

                        $id = (int) $name;

                        // Same sprite exported by several files: keep the first one
                        if (isset($done[$id]) || ($only !== null && !isset($only[$id]))) {
                            continue;
                        }

                        $done[$id] = true;

                        if (!$force && $this->files->exists($set, $id)) {
                            ++$report['skipped'];
                        } else {
                            try {
                                $character = $extractor->byName((string) $name);

                                if (!$character instanceof DrawableInterface) {
                                    throw new RuntimeException('Not a drawable character');
                                }

                                $this->files->save($set, $id, $this->renderer->render($this->converter->toSvg($character)));
                                ++$report['exported'];
                            } catch (Throwable $e) {
                                $report['failed'][$id] = $e->getMessage();
                            }

                            $extractor->releaseIfOutOfMemory();
                        }

                        if ($onSprite) {
                            $onSprite($id, !isset($report['failed'][$id]));
                        }
                    }
                } finally {
                    $extractor->release();
                }
            }
        } finally {
            $this->files->flush($set);
        }

        return $report;
    }
}
