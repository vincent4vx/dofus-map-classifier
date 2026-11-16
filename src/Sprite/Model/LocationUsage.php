<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

/**
 * Area or sub area where a sprite is used, with the number of maps using it there
 *
 * JSON: {"id": 18, "name": "Astrub", "maps": 42}
 */
final readonly class LocationUsage implements JsonSerializable
{
    public function __construct(
        public int $id,
        /** Name from the langs, null if not available */
        public ?string $name,
        /** Number of maps of this location using the sprite */
        public int $maps,
    ) {}

    /**
     * Name to display: the name, or "#id" if unknown
     */
    public function label(): string
    {
        return $this->name ?? '#' . $this->id;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: isset($data['name']) ? (string) $data['name'] : null,
            maps: (int) ($data['maps'] ?? 0),
        );
    }

    /**
     * @return array{id: int, name: string|null, maps: int}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'maps' => $this->maps];
    }
}
