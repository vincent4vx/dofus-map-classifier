<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model\Export;

use JsonSerializable;
use MapClassifier\Sprite\Classification\Model\ObjectTypeClassification;
use MapClassifier\Sprite\Model\ObjectProfile;
use Override;

/**
 * Sprite of the export file (objects.json): its profile, with its type
 *
 * JSON: the fields of {@see ObjectProfile}, then "type" ({@see ObjectTypeClassification}), null if not classified
 */
final readonly class ExportedSprite implements JsonSerializable
{
    public function __construct(
        public ObjectProfile $profile,
        /** Type of the sprite, with the interactive object name applied (see {@see \MapClassifier\Sprite\Classification\Service\ObjectTypeResolver}) */
        public ?ObjectTypeClassification $type,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->profile->jsonSerialize() + ['type' => $this->type];
    }
}
