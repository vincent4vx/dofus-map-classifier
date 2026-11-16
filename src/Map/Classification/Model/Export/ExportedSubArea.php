<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Model\Export;

use JsonSerializable;
use MapClassifier\Map\Classification\Model\Biome;
use Override;

/**
 * Sub area of an exported map, with the biome deduced from all its maps
 *
 * JSON: {"id": 320, "name": "Kwismas Island", "biome": "snow"}
 */
final readonly class ExportedSubArea implements JsonSerializable
{
    public function __construct(
        public int $id,
        public ?string $name,
        /** Biome of the sub area (see {@see \MapClassifier\Map\Classification\Model\SubAreaBiome}), null if unknown */
        public ?Biome $biome,
    ) {}

    /**
     * @return array{id: int, name: string|null, biome: string|null}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'biome' => $this->biome?->value];
    }
}
