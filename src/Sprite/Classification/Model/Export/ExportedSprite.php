<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model\Export;

use JsonSerializable;
use MapClassifier\Sprite\Classification\Model\SpriteType;
use MapClassifier\Sprite\Model\SpriteProfile;
use Override;

/**
 * Sprite of the export file (sprites.json): its profile, with its type
 *
 * JSON: the fields of {@see SpriteProfile}, then "type" ({@see SpriteType}), null if not classified
 */
final readonly class ExportedSprite implements JsonSerializable
{
    public function __construct(
        public SpriteProfile $profile,
        /** Type of the sprite, with the interactive object name applied (see {@see \MapClassifier\Sprite\Classification\Service\SpriteTypeResolver}) */
        public ?SpriteType $type,
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
