<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use InvalidArgumentException;

use function preg_match;

/**
 * Selection of sprites by ids: "345" (both sets), "o345" (objects), "g10-20" (grounds range)
 */
final readonly class SpriteSelection
{
    private function __construct(
        /** @var list<array{SpriteSet|null, int, int}> Set (null for both), min id, max id */
        private array $ranges,
    ) {}

    /**
     * Select all sprites
     */
    public static function all(): self
    {
        return new self([]);
    }

    /**
     * @param list<string> $arguments
     *
     * @throws InvalidArgumentException
     */
    public static function parse(array $arguments): self
    {
        $ranges = [];

        foreach ($arguments as $argument) {
            if (!preg_match('/^([go]?)(\d+)(?:-(\d+))?$/', $argument, $m)) {
                throw new InvalidArgumentException('Invalid sprite id or range "' . $argument . '" (expected e.g. "o345", "g10-20" or "345")');
            }

            $ranges[] = [
                match ($m[1]) {
                    'g' => SpriteSet::Grounds,
                    'o' => SpriteSet::Objects,
                    default => null,
                },
                (int) $m[2],
                isset($m[3]) ? (int) $m[3] : (int) $m[2],
            ];
        }

        return new self($ranges);
    }

    /**
     * Check if the sprite is selected. All sprites are selected by an empty selection.
     */
    public function matches(SpriteSet $set, int $id): bool
    {
        if (!$this->ranges) {
            return true;
        }

        foreach ($this->ranges as [$rangeSet, $min, $max]) {
            if (($rangeSet === null || $rangeSet === $set) && $id >= $min && $id <= $max) {
                return true;
            }
        }

        return false;
    }
}
