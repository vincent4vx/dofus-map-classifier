<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\SpriteType;
use MapClassifier\Sprite\Classification\Model\SpriteTypeResult;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteSet;

/**
 * Resolve the type of a sprite from the stored classifications
 * The name of the interactive object (from the langs) takes priority over the name given by the model
 *
 * Stored types are loaded once: create a new instance to see types classified later
 */
final class SpriteTypeResolver
{
    /** @var array<string, array<int, SpriteTypeResult>> */
    private array $types = [];

    public function __construct(
        private readonly SpriteTypeRepository $repository,
    ) {}

    public function type(SpriteProfile $profile): ?SpriteType
    {
        return self::withInteractiveName($profile, $this->classification($profile->set, $profile->id));
    }

    /**
     * Stored classification of a sprite, without the name of its interactive object
     */
    public function classification(SpriteSet $set, int $id): ?SpriteType
    {
        $this->types[$set->value] ??= $this->repository->all($set);

        return ($this->types[$set->value][$id] ?? null)?->classification;
    }

    /**
     * Apply the name of the interactive object on the classification
     * The model name and the interactive object type are kept as keywords
     */
    public static function withInteractiveName(SpriteProfile $profile, ?SpriteType $classification): ?SpriteType
    {
        if ($classification === null || $profile->interactive === null) {
            return $classification;
        }

        return $classification->withName($profile->interactive->name, $profile->interactive->typeName());
    }
}
