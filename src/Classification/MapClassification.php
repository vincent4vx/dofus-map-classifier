<?php

declare(strict_types=1);

namespace MapClassifier\Classification;

use InvalidArgumentException;

use function array_map;
use function array_values;
use function is_array;
use function is_bool;
use function is_string;
use function trim;

/**
 * Classification of a map returned by the model
 */
final readonly class MapClassification
{
    public function __construct(
        /** Null when it cannot be determined (e.g. building interior) */
        public ?Biome $biome,
        public ?string $biomeOther,
        public bool $indoor,
        public LocationType $locationType,
        /** @var list<string> */
        public array $tags,
        public string $description,
    ) {}

    /**
     * Create from the decoded model output (fields named like the JSON schema)
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidArgumentException When the data is invalid
     */
    public static function fromArray(array $data): self
    {
        $biome = ($data['biome'] ?? null) === null ? null : (
            Biome::tryFrom(self::string($data, 'biome'))
                ?? throw new InvalidArgumentException('Invalid biome: ' . self::string($data, 'biome'))
        );
        $locationType = LocationType::tryFrom(self::string($data, 'location_type'))
            ?? throw new InvalidArgumentException('Invalid location_type: ' . self::string($data, 'location_type'));

        $biomeOther = $data['biome_other'] ?? null;

        if ($biomeOther !== null && !is_string($biomeOther)) {
            throw new InvalidArgumentException('Invalid biome_other');
        }

        $biomeOther = $biomeOther !== null && trim($biomeOther) !== '' ? trim($biomeOther) : null;

        if (!is_bool($data['indoor'] ?? null)) {
            throw new InvalidArgumentException('Invalid indoor');
        }

        $tags = $data['tags'] ?? null;

        if (!is_array($tags)) {
            throw new InvalidArgumentException('Invalid tags');
        }

        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                throw new InvalidArgumentException('Invalid tags');
            }
        }

        return new self(
            $biome,
            $biome === Biome::Other ? $biomeOther : null,
            $data['indoor'],
            $locationType,
            array_values(array_map(static fn (string $tag) => trim($tag), $tags)),
            trim(self::string($data, 'description')),
        );
    }

    /**
     * @return array{biome: string|null, biome_other: string|null, indoor: bool, location_type: string, tags: list<string>, description: string}
     */
    public function toArray(): array
    {
        return [
            'biome' => $this->biome?->value,
            'biome_other' => $this->biomeOther,
            'indoor' => $this->indoor,
            'location_type' => $this->locationType->value,
            'tags' => $this->tags,
            'description' => $this->description,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : throw new InvalidArgumentException('Invalid ' . $key);
    }
}
