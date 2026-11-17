<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

use function array_map;
use function array_sum;
use function count;

/**
 * Sprites used by a map, with their usage (see {@see \MapClassifier\Sprite\Service\MapSpriteExtractor})
 *
 * @implements IteratorAggregate<int, MapSpriteUsage>
 */
final class MapSprites implements IteratorAggregate, Countable
{
    /** @var array<string, array<int, MapSpriteUsage>> Indexed by set value, then sprite id */
    private array $usages = [];

    /**
     * Count a cell (or the background) using the sprite
     *
     * @param bool $sloped The cell is a ground cell with a slope
     * @param int|null $movement Movement of the cell, null for the background
     */
    public function addCell(Layer $layer, int $id, bool $sloped = false, ?int $movement = null): void
    {
        $set = $layer->spriteSet();
        $this->usages[$set->value][$id] ??= new MapSpriteUsage($set, $id);
        $this->usages[$set->value][$id]->addCell($layer, $sloped, $movement);
    }

    public function get(SpriteSet $set, int $id): ?MapSpriteUsage
    {
        return $this->usages[$set->value][$id] ?? null;
    }

    /**
     * Number of different sprites
     */
    #[Override]
    public function count(): int
    {
        return array_sum(array_map(count(...), $this->usages));
    }

    /**
     * One usage per sprite, in the order of first use
     */
    #[Override]
    public function getIterator(): Traversable
    {
        foreach ($this->usages as $usages) {
            yield from $usages;
        }
    }
}
