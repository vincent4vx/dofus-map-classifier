<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use MapClassifier\Map\Model\CellOffset;

use function explode;
use function round;
use function strnatcmp;
use function uksort;

/**
 * Count the neighbors of the cells using a sprite with a given orientation, on the same layer, at each offset
 * (see {@see MapSpriteUsage::addNeighbors()}, aggregated by {@see SpriteStats})
 */
final class NeighborCounts
{
    /** Neighbors found on less than this ratio of the cells are ignored */
    public const float MIN_FREQUENCY = 0.1;

    /** Neighbors found less than this number of times are ignored */
    public const int MIN_COUNT = 2;

    /** Number of cells using the sprite with this orientation */
    public private(set) int $cells = 0;

    /** @var array<string, int> Number of cells having the neighbor, indexed by "{@see CellOffset::key()} {@see OrientedSprite::key()}" */
    private array $counts = [];

    public function __construct(
        public readonly Orientation $orientation,
    ) {}

    /**
     * Count a cell using the sprite
     *
     * @param array<string, OrientedSprite> $neighbors Sprites on the cells around, on the same layer, indexed by {@see CellOffset::key()}
     */
    public function addCell(array $neighbors): void
    {
        ++$this->cells;

        foreach ($neighbors as $offset => $neighbor) {
            $key = $offset . ' ' . $neighbor->key();
            $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
        }
    }

    /**
     * Add the counts of the same sprite and orientation (e.g. on another map)
     */
    public function merge(self $other): void
    {
        $this->cells += $other->cells;

        foreach ($other->counts as $key => $count) {
            $this->counts[$key] = ($this->counts[$key] ?? 0) + $count;
        }
    }

    /**
     * The frequent neighbors, sorted by count desc
     * Neighbors found less than {@see MIN_COUNT} times, or on less than {@see MIN_FREQUENCY} of the cells are ignored
     */
    public function result(): OrientationNeighbors
    {
        $counts = $this->counts;
        // Sorted by key on equality, so the result does not depend on the reading order
        uksort($counts, static fn (int|string $a, int|string $b) => $counts[$b] <=> $counts[$a] ?: strnatcmp((string) $a, (string) $b));
        $neighbors = [];

        foreach ($counts as $key => $count) {
            $frequency = $count / $this->cells;

            if ($count < self::MIN_COUNT || $frequency < self::MIN_FREQUENCY) {
                break;
            }

            [$offset, $sprite] = explode(' ', (string) $key, 2);
            $neighbors[] = new Neighbor(CellOffset::fromKey($offset), OrientedSprite::fromKey($sprite), $count, round($frequency, 4));
        }

        return new OrientationNeighbors($this->orientation, $this->cells, $neighbors);
    }
}
