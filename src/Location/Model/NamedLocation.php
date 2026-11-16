<?php

declare(strict_types=1);

namespace MapClassifier\Location\Model;

use JsonSerializable;
use Override;

/**
 * Reference to a sub area, an area or a super area
 *
 * JSON: {"id": 95, "name": "Astrub City"}
 */
final readonly class NamedLocation implements JsonSerializable
{
    public function __construct(
        public int $id,
        /** Name from the langs, null if not available */
        public ?string $name,
    ) {}

    /**
     * @return array{id: int, name: string|null}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
