<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Service;

use Arakne\Swf\Extractor\Drawer\Converter\Converter;
use Arakne\Swf\Extractor\Sprite\SpriteDefinition;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Repository\SpriteRepository;
use Throwable;

/**
 * Export ground and object sprites of the client to the PNG files sent to the model, with their metadata (size, frames)
 * Sprites are extracted as SVG with ArakneSwf, then rendered with {@see SpriteRenderer}
 */
final readonly class SpriteImageExporter
{
    public function __construct(
        private SpriteRepository $sprites,
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

        try {
            foreach ($this->sprites->client($set, $only) as $id => $load) {
                if (!$force && $this->sprites->get($set, $id) !== null) {
                    ++$report['skipped'];
                } else {
                    try {
                        $sprite = $load();
                        $frames = $sprite instanceof SpriteDefinition ? $sprite->framesCount() : 1;
                        $this->sprites->save($set, $id, $this->renderer->render($this->converter->toSvg($sprite)), $frames);
                        ++$report['exported'];
                    } catch (Throwable $e) {
                        $report['failed'][$id] = $e->getMessage();
                    }
                }

                if ($onSprite) {
                    $onSprite($id, !isset($report['failed'][$id]));
                }
            }
        } finally {
            $this->sprites->flush($set);
        }

        return $report;
    }
}
