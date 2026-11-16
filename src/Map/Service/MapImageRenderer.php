<?php

declare(strict_types=1);

namespace MapClassifier\Map\Service;

use Arakne\MapParser\DofusMapParser;
use Arakne\MapParser\Loader\MapStructure;
use Arakne\MapParser\Sprite\Cache\SqliteSpriteCache;
use Arakne\Swf\SwfFile;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Model\RenderError;
use MapClassifier\Map\Repository\MapKeyRepository;
use RuntimeException;
use Throwable;

use function dirname;
use function imagepng;
use function is_dir;
use function mkdir;
use function rename;

/**
 * Render client maps to PNG files using php-map-parser
 */
final class MapImageRenderer
{
    private DofusMapParser $parser {
        get => $this->parser ??= new DofusMapParser(
            dofusPath: $this->dofusPath,
            spriteCache: new SqliteSpriteCache($this->cacheDir . '/sprites.db', inMemoryCapacity: 500),
        );
    }

    public function __construct(
        private readonly string $dofusPath,
        private readonly string $cacheDir,
        private readonly MapKeyRepository $keys,
    ) {}

    /**
     * Render the map and save it as PNG to $target
     *
     * @throws RenderException
     */
    public function renderTo(MapFile $map, string $target): void
    {
        $attachments = [];

        if ($map->encrypted) {
            $attachments[] = $this->keys->find($map)
                ?? throw new RenderException(RenderError::MissingKey, 'No key found for map ' . $map->id . ' version ' . $map->version);
        }

        try {
            $structure = MapStructure::fromSwfFile(new SwfFile($map->path), ...$attachments);
            $image = $this->parser->render($structure);
        } catch (Throwable $e) {
            throw new RenderException(RenderError::RenderFailed, $e->getMessage(), $e);
        }

        $dir = dirname($target);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory ' . $dir);
        }

        // Write in a temporary file to never keep a partial image on crash
        $tmp = $target . '.tmp';

        if (!imagepng($image, $tmp) || !rename($tmp, $target)) {
            throw new RuntimeException('Cannot write image ' . $target);
        }
    }
}
