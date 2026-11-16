<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\ObjectTypeClassification;
use MapClassifier\Sprite\Classification\Model\ObjectTypeResult;
use MapClassifier\Sprite\Classification\Repository\ObjectTypeRepository;
use MapClassifier\Sprite\Model\ObjectProfile;

/**
 * Resolve the type of a sprite from the stored classifications
 * The name of the interactive object (from the langs) takes priority over the name given by the model
 *
 * Stored types are loaded once: create a new instance to see types classified later
 */
final class ObjectTypeResolver
{
    /** @var array<string, array<int, ObjectTypeResult>> */
    private array $types = [];

    public function __construct(
        private readonly ObjectTypeRepository $repository,
    ) {}

    public function type(ObjectProfile $profile): ?ObjectTypeClassification
    {
        $this->types[$profile->set->value] ??= $this->repository->all($profile->set);

        return self::withInteractiveName($profile, ($this->types[$profile->set->value][$profile->id] ?? null)?->classification);
    }

    /**
     * Fields joined to the profile when it is stored
     *
     * @return array{type: array<string, mixed>|null}
     */
    public function fields(ObjectProfile $profile): array
    {
        return ['type' => $this->type($profile)?->toArray()];
    }

    /**
     * Apply the name of the interactive object on the classification
     * The model name and the interactive object type are kept as keywords
     */
    public static function withInteractiveName(ObjectProfile $profile, ?ObjectTypeClassification $classification): ?ObjectTypeClassification
    {
        if ($classification === null || $profile->interactive === null) {
            return $classification;
        }

        return $classification->withName($profile->interactive->name, $profile->interactive->typeName());
    }
}
