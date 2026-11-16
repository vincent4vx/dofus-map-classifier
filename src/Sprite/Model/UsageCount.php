<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

use function is_array;
use function is_int;

/**
 * Usage of a sprite in a context (biome, area, location type…): number of maps, and total number of uses on these maps
 *
 * JSON: {"maps": 30, "occurrences": 120}
 */
final readonly class UsageCount implements JsonSerializable
{
    public function __construct(
        /** Number of maps using the sprite */
        public int $maps = 0,
        /** Total number of cells (or backgrounds) using the sprite on these maps */
        public int $occurrences = 0,
    ) {}

    /**
     * Count one more map using the sprite
     *
     * @param int $occurrences Number of uses of the sprite on the map
     */
    public function withMap(int $occurrences): self
    {
        return new self($this->maps + 1, $this->occurrences + $occurrences);
    }

    /**
     * Average number of uses on a map using the sprite
     */
    public function perMap(): float
    {
        return $this->maps > 0 ? $this->occurrences / $this->maps : 0.0;
    }

    /**
     * Parse the JSON of {@see jsonSerialize()}. A number is read as the number of maps (format without occurrences)
     */
    public static function fromJson(mixed $data): self
    {
        if (is_int($data)) {
            return new self($data);
        }

        return is_array($data) ? new self((int) ($data['maps'] ?? 0), (int) ($data['occurrences'] ?? 0)) : new self();
    }

    /**
     * @return array{maps: int, occurrences: int}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['maps' => $this->maps, 'occurrences' => $this->occurrences];
    }
}
