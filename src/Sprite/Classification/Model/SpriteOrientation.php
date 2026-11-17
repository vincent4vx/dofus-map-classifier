<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model;

/**
 * Direction a sprite points to, on the screen, as drawn by default (not flipped, not rotated)
 *
 * For a transition, the direction of the other environment or material. For other sprites, the direction it is used toward:
 * the door of a house, the seat of a chair, the visible face of a wall…
 * Not to be confused with {@see \MapClassifier\Sprite\Model\Orientation}, the rotation and flip of a sprite drawn on a cell.
 */
enum SpriteOrientation: string
{
    case Top = 'top';
    case TopRight = 'top_right';
    case Right = 'right';
    case BottomRight = 'bottom_right';
    case Bottom = 'bottom';
    case BottomLeft = 'bottom_left';
    case Left = 'left';
    case TopLeft = 'top_left';

    /**
     * Orientation of the sprite drawn flipped (horizontal mirror): left and right are swapped
     */
    public function flipped(): self
    {
        return match ($this) {
            self::TopRight => self::TopLeft,
            self::Right => self::Left,
            self::BottomRight => self::BottomLeft,
            self::BottomLeft => self::BottomRight,
            self::Left => self::Right,
            self::TopLeft => self::TopRight,
            self::Top, self::Bottom => $this,
        };
    }
}
