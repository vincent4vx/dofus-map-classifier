<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Model\Export;

use JsonSerializable;
use MapClassifier\Map\Classification\Model\MapResult;
use Override;

/**
 * Map of the export file (maps.json): the map result, with its location
 *
 * JSON: the fields of {@see MapResult}, with "location" ({@see ExportedLocation}, null if unknown) after the version:
 * {"id", "version", "location", "status", "error", "error_message", "biome", "biome_other", "indoor", "location_type", "tags", "description", "model", "classified_at"}
 */
final readonly class ExportedMap implements JsonSerializable
{
    public function __construct(
        public MapResult $result,
        public ?ExportedLocation $location,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->result->id,
            'version' => $this->result->version,
            'location' => $this->location,
        ] + $this->result->jsonSerialize();
    }
}
