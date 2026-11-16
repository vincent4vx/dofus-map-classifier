<?php

declare(strict_types=1);

namespace MapClassifier\Map\Repository;

use MapClassifier\Map\Model\MapFile;

use function basename;
use function glob;
use function ksort;
use function preg_match;

/**
 * List map files from the client "data/maps" directory
 * Files are named "{id}_{version}.swf", or "{id}_{version}X.swf" for encrypted maps
 */
final class MapFileRepository
{
    /** @var array<int, MapFile>|null */
    private ?array $files = null;

    public function __construct(
        private readonly string $mapsDir,
    ) {}

    /**
     * @return array<int, MapFile> Map files indexed by id, sorted by id
     */
    public function all(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }

        $files = [];

        foreach (glob($this->mapsDir . '/*.swf') ?: [] as $path) {
            if (!preg_match('/^(\d+)_(.+?)(X?)\.swf$/', basename($path), $matches)) {
                continue;
            }

            $id = (int) $matches[1];
            $files[$id] = new MapFile($id, $matches[2], $matches[3] === 'X', $path);
        }

        ksort($files);

        return $this->files = $files;
    }

    public function get(int $id): ?MapFile
    {
        return $this->all()[$id] ?? null;
    }
}
