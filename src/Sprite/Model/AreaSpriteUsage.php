<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

use function is_array;
use function is_numeric;

/**
 * Usage of a sprite on the maps of one location type of an area (see {@see AreaSprite::$byLocationType})
 *
 * JSON: {"maps": int, "occurrences": int, "ground_share": float | null, "bare_share": float | null, "path": {@see PathProximity} | null, "cover_share": float | null}
 */
final readonly class AreaSpriteUsage implements JsonSerializable
{
    public function __construct(
        /** Number of maps of this type using the sprite */
        public int $maps,
        /** Total number of cells (or backgrounds) using the sprite on these maps */
        public int $occurrences,
        /** As {@see SpriteProfile::$groundShare}, on these maps */
        public ?float $groundShare = null,
        /** As {@see SpriteProfile::$bareShare}, on these maps */
        public ?float $bareShare = null,
        /** As {@see SpriteProfile::$path}, on these maps */
        public ?PathProximity $path = null,
        /** As {@see SpriteProfile::$coverShare}, on these maps */
        public ?float $coverShare = null,
    ) {}

    public static function of(SpriteStats $stats, Layer $layer): self
    {
        return new self(
            $stats->maps,
            $stats->occurrences(),
            $layer === Layer::Ground ? $stats->groundShare() : null,
            $layer === Layer::Background ? $stats->bareShare() : null,
            $stats->pathProximity(),
            $stats->coverShare(),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['maps'] ?? 0),
            (int) ($data['occurrences'] ?? 0),
            is_numeric($data['ground_share'] ?? null) ? (float) $data['ground_share'] : null,
            is_numeric($data['bare_share'] ?? null) ? (float) $data['bare_share'] : null,
            is_array($data['path'] ?? null) ? PathProximity::fromArray($data['path']) : null,
            is_numeric($data['cover_share'] ?? null) ? (float) $data['cover_share'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'maps' => $this->maps,
            'occurrences' => $this->occurrences,
            'ground_share' => $this->groundShare,
            'bare_share' => $this->bareShare,
            'path' => $this->path,
            'cover_share' => $this->coverShare,
        ];
    }
}
