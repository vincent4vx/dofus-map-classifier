<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model\Export;

use JsonSerializable;
use MapClassifier\Sprite\Classification\Model\SpriteType;
use MapClassifier\Sprite\Model\SpriteMetadata;
use MapClassifier\Sprite\Model\SpriteProfile;
use Override;

/**
 * Sprite of the export file (sprites.json): its profile, with its type
 *
 * JSON: the fields of {@see SpriteProfile}, then "image" ({@see SpriteMetadata}, null if not exported),
 * "example_image" (path of the example map, relative to the examples directory, null if not rendered) and "type" ({@see SpriteType}, null if not classified)
 */
final readonly class ExportedSprite implements JsonSerializable
{
    public function __construct(
        public SpriteProfile $profile,
        /** Metadata of the exported image, null if not exported */
        public ?SpriteMetadata $metadata,
        /** Type of the sprite, with the interactive object name applied (see {@see \MapClassifier\Sprite\Classification\Service\SpriteTypeResolver}) */
        public ?SpriteType $type,
        /** Path of the example map image, relative to the examples directory, null if not rendered (see {@see \MapClassifier\Sprite\Repository\SpriteExampleRepository}) */
        public ?string $exampleImage = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->profile->jsonSerialize() + [
            'image' => $this->metadata,
            'example_image' => $this->exampleImage,
            'type' => $this->type,
        ];
    }
}
