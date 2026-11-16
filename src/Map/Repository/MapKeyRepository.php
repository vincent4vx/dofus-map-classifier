<?php

declare(strict_types=1);

namespace MapClassifier\Map\Repository;

use Arakne\MapParser\Loader\MapKey;
use MapClassifier\Map\Model\MapFile;

use function is_file;

/**
 * Resolve map keys stored as "{id}_{version}.txt" files (MapKeys-DR layout)
 */
final readonly class MapKeyRepository
{
    public function __construct(
        private string $keysDir,
    ) {}

    public function find(MapFile $map): ?MapKey
    {
        $file = $this->keysDir . '/' . $map->id . '_' . $map->version . '.txt';

        return is_file($file) ? MapKey::fromFile($file) : null;
    }
}
