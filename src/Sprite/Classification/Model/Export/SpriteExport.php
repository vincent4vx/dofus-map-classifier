<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model\Export;

use Countable;
use IteratorAggregate;
use JsonSerializable;
use MapClassifier\Sprite\Model\SpriteSet;
use Override;
use Traversable;

use function array_filter;
use function array_map;
use function count;

/**
 * Export of all sprites with their type (sprites.json)
 *
 * JSON: {"grounds": {id: {@see ExportedSprite}}, "objects": {id: {@see ExportedSprite}}}
 *
 * @implements IteratorAggregate<int, ExportedSprite>
 */
final readonly class SpriteExport implements IteratorAggregate, Countable, JsonSerializable
{
    public function __construct(
        /** @var list<ExportedSprite> Ordered by set, then sprite id */
        public array $sprites,
    ) {}

    public function get(SpriteSet $set, int $id): ?ExportedSprite
    {
        foreach ($this->sprites as $sprite) {
            if ($sprite->profile->set === $set && $sprite->profile->id === $id) {
                return $sprite;
            }
        }

        return null;
    }

    /**
     * Total number of sprites
     */
    #[Override]
    public function count(): int
    {
        return count($this->sprites);
    }

    /**
     * Number of sprites with a type
     */
    public function classified(): int
    {
        return count(array_filter($this->sprites, static fn (ExportedSprite $sprite) => $sprite->type !== null));
    }

    #[Override]
    public function getIterator(): Traversable
    {
        yield from $this->sprites;
    }

    /**
     * @return array<string, object>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $bySet = [];

        foreach (SpriteSet::cases() as $set) {
            $bySet[$set->value] = [];
        }

        foreach ($this->sprites as $sprite) {
            $bySet[$sprite->profile->set->value][$sprite->profile->id] = $sprite;
        }

        // Keep sprites indexed by id (JSON object) even if ids are sequential
        return array_map(static fn (array $sprites) => (object) $sprites, $bySet);
    }
}
