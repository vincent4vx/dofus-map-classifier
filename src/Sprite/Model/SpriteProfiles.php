<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use Countable;
use IteratorAggregate;
use JsonSerializable;
use Override;
use Traversable;

use function array_map;
use function array_sum;
use function count;

/**
 * Profiles of the sprites, indexed by set then sprite id
 *
 * JSON (sprite_profiles.json): {"grounds": {id: {@see SpriteProfile}}, "objects": {id: {@see SpriteProfile}}}
 *
 * @implements IteratorAggregate<int, SpriteProfile>
 */
final readonly class SpriteProfiles implements IteratorAggregate, Countable, JsonSerializable
{
    private function __construct(
        /** @var array<string, array<int, SpriteProfile>> Profiles indexed by set value, then sprite id */
        private array $profiles,
    ) {}

    /**
     * Index the profiles by set and id, keeping their order inside each set
     *
     * @param iterable<SpriteProfile> $profiles
     */
    public static function of(iterable $profiles): self
    {
        $bySet = [];

        foreach (SpriteSet::cases() as $set) {
            $bySet[$set->value] = [];
        }

        foreach ($profiles as $profile) {
            $bySet[$profile->set->value][$profile->id] = $profile;
        }

        return new self($bySet);
    }

    public function get(SpriteSet $set, int $id): ?SpriteProfile
    {
        return $this->profiles[$set->value][$id] ?? null;
    }

    /**
     * @return array<int, SpriteProfile> Profiles of the set, indexed by sprite id
     */
    public function bySet(SpriteSet $set): array
    {
        return $this->profiles[$set->value] ?? [];
    }

    /**
     * Total number of sprites
     */
    #[Override]
    public function count(): int
    {
        return array_sum(array_map(count(...), $this->profiles));
    }

    /**
     * All profiles, ordered by set then by their order in the set
     */
    #[Override]
    public function getIterator(): Traversable
    {
        foreach ($this->profiles as $profiles) {
            foreach ($profiles as $profile) {
                yield $profile;
            }
        }
    }

    /**
     * @return array<string, object>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        // Keep sprites indexed by id (JSON object) even if ids are sequential
        return array_map(static fn (array $profiles) => (object) $profiles, $this->profiles);
    }
}
