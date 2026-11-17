<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Service;

use GdImage;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\Rectangle;
use MapClassifier\Map\Repository\MapFileRepository;
use MapClassifier\Map\Service\MapImageRenderer;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Repository\SpriteExampleRepository;
use RuntimeException;

use function imagecolorallocate;
use function imagecreatefrompng;
use function imagecreatetruecolor;
use function imagecopy;
use function imagefilledrectangle;
use function imagesx;
use function imagesy;
use function is_file;
use function max;
use function min;

/**
 * Render the example map of the sprites: the map of {@see SpriteProfile::$example}, with a magenta frame around the sprite,
 * so the model can see the sprite in its context. Magenta is not used by the maps, so the frame cannot be mistaken for the map.
 */
final readonly class SpriteExampleRenderer
{
    /** Width of the frame, in pixels */
    public const int FRAME_WIDTH = 3;

    /** Space between the sprite and the frame, in pixels */
    public const int FRAME_MARGIN = 2;

    public function __construct(
        private MapFileRepository $maps,
        private MapImageRenderer $renderer,
        private SpriteExampleRepository $examples,
        /** Directory of the rendered maps (see maps:render), reused when available */
        private string $rendersDir,
    ) {}

    /**
     * Render the examples of the sprites, map by map
     *
     * @param iterable<SpriteProfile> $profiles Sprites without example (backgrounds) are ignored
     * @param bool $force Render again the already rendered examples
     * @param callable(SpriteProfile): void|null $onSprite Called after each sprite with an example
     *
     * @return array{rendered: int, skipped: int, failed: array<string, string>} Failures indexed by sprite name
     */
    public function render(iterable $profiles, bool $force = false, ?callable $onSprite = null): array
    {
        $report = ['rendered' => 0, 'skipped' => 0, 'failed' => []];
        $byMap = [];

        foreach ($profiles as $profile) {
            if ($profile->example === null) {
                continue;
            }

            if (!$force && $this->examples->exists($profile)) {
                ++$report['skipped'];

                if ($onSprite) {
                    $onSprite($profile);
                }
            } else {
                $byMap[$profile->example->mapId][$profile->example->cellId][] = $profile;
            }
        }

        foreach ($byMap as $mapId => $sprites) {
            try {
                $file = $this->maps->get($mapId) ?? throw new RuntimeException('Map ' . $mapId . ' not found');
                $map = $this->renderer->load($file);
                $render = $this->rendersDir . '/' . $mapId . '.png';
                $image = (is_file($render) ? imagecreatefrompng($render) : null) ?: $this->renderer->render($map);
            } catch (RenderException|RuntimeException $e) {
                foreach ($sprites as $cellSprites) {
                    foreach ($cellSprites as $profile) {
                        $report['failed'][$profile->set->spriteName($profile->id)] = $e->getMessage();

                        if ($onSprite) {
                            $onSprite($profile);
                        }
                    }
                }

                continue;
            }

            foreach ($sprites as $cellId => $cellSprites) {
                foreach ($cellSprites as $profile) {
                    $cell = $map->cells[$cellId] ?? null;
                    $object = $cell !== null ? $profile->layer->object($cell) : null;
                    $name = $profile->set->spriteName($profile->id);

                    if ($object === null || $object->number !== $profile->id) {
                        $report['failed'][$name] = 'Sprite not found on the cell ' . $cellId . ' of the map ' . $mapId;
                    } elseif (($bounds = $this->renderer->spriteBounds($map, $cellId, $object)) === null) {
                        $report['failed'][$name] = 'Sprite not drawn on the map: empty or missing in the client';
                    } else {
                        $this->examples->save($profile, self::framed($image, $bounds));
                        ++$report['rendered'];
                    }

                    if ($onSprite) {
                        $onSprite($profile);
                    }
                }
            }
        }

        return $report;
    }

    /**
     * Copy of the image with a magenta frame around the area
     * The frame is kept inside the image, so it stays visible for a sprite on the border of the map
     */
    public static function framed(GdImage $image, Rectangle $area): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $framed = imagecreatetruecolor($width, $height) ?: throw new RuntimeException('Cannot create image');
        imagecopy($framed, $image, 0, 0, 0, 0, $width, $height);

        // Outer bounds of the frame, kept inside the image
        $left = max(0, $area->x - self::FRAME_MARGIN - self::FRAME_WIDTH);
        $top = max(0, $area->y - self::FRAME_MARGIN - self::FRAME_WIDTH);
        $right = min($width - 1, $area->x + $area->width - 1 + self::FRAME_MARGIN + self::FRAME_WIDTH);
        $bottom = min($height - 1, $area->y + $area->height - 1 + self::FRAME_MARGIN + self::FRAME_WIDTH);
        $color = imagecolorallocate($framed, 255, 0, 255) ?: 0;
        $inner = self::FRAME_WIDTH - 1;

        imagefilledrectangle($framed, $left, $top, $right, $top + $inner, $color);
        imagefilledrectangle($framed, $left, $bottom - $inner, $right, $bottom, $color);
        imagefilledrectangle($framed, $left, $top, $left + $inner, $bottom, $color);
        imagefilledrectangle($framed, $right - $inner, $top, $right, $bottom, $color);

        return $framed;
    }
}
