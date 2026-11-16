<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

/**
 * Criteria used to select sprites from their profile
 */
final readonly class SpriteFilter
{
    public SpriteSelection $selection;

    public function __construct(
        /** Selected sprite ids, all sprites by default */
        ?SpriteSelection $selection = null,
        /** Only sprites of this set */
        public ?SpriteSet $set = null,
        /** Only sprites mainly used on this layer */
        public ?Layer $layer = null,
        /** Only sprites used on at least this number of maps */
        public int $minMaps = 0,
        /** Only sprites of interactive objects */
        public bool $interactiveOnly = false,
    ) {
        $this->selection = $selection ?? SpriteSelection::all();
    }

    public function matches(SpriteProfile $profile): bool
    {
        return ($this->set === null || $profile->set === $this->set)
            && ($this->layer === null || $profile->layer === $this->layer)
            && $profile->maps >= $this->minMaps
            && (!$this->interactiveOnly || $profile->interactive !== null)
            && $this->selection->matches($profile->set, $profile->id);
    }

    /**
     * @return list<SpriteProfile> Matching profiles, in the order of the collection
     */
    public function filter(SpriteProfiles $profiles): array
    {
        $matching = [];

        foreach ($this->set !== null ? $profiles->bySet($this->set) : $profiles as $profile) {
            if ($this->matches($profile)) {
                $matching[] = $profile;
            }
        }

        return $matching;
    }
}
