<?php

declare(strict_types=1);

namespace MapClassifier\Map\Model;

/**
 * The eight cells around a cell, by their direction on the screen
 *
 * The four sides (north east, south east, south west, north west) share an edge of the diamond with the cell,
 * the four corners (north, east, south, west) only one of its points.
 */
enum Direction: string
{
    case North = 'n';
    case NorthEast = 'ne';
    case East = 'e';
    case SouthEast = 'se';
    case South = 's';
    case SouthWest = 'sw';
    case West = 'w';
    case NorthWest = 'nw';

    /**
     * Offset of the cell in this direction, on the axes of {@see CellGrid} (x to the south east, y to the south west)
     *
     * @return array{int, int}
     */
    public function offset(): array
    {
        return match ($this) {
            self::North => [-1, -1],
            self::NorthEast => [0, -1],
            self::East => [1, -1],
            self::SouthEast => [1, 0],
            self::South => [1, 1],
            self::SouthWest => [0, 1],
            self::West => [-1, 1],
            self::NorthWest => [-1, 0],
        };
    }

    /**
     * Key of a set of directions: their values in the order of the cases, separated by a space ("" for none)
     *
     * @param list<self> $directions
     */
    public static function key(array $directions): string
    {
        $values = [];

        foreach (self::cases() as $direction) {
            if (in_array($direction, $directions, true)) {
                $values[] = $direction->value;
            }
        }

        return implode(' ', $values);
    }
}
