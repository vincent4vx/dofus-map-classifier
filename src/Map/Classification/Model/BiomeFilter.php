<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Model;

use InvalidArgumentException;

use function array_column;
use function implode;
use function sprintf;

/**
 * Select classified maps by biome, or classified maps without biome
 */
final readonly class BiomeFilter
{
    /**
     * Value used to select classified maps without biome
     */
    public const string NONE = 'none';

    public function __construct(
        /** Biome of the maps to select, or null for maps without biome */
        public ?Biome $biome,
    ) {}

    /**
     * Parse a biome value, or {@see NONE}
     *
     * @throws InvalidArgumentException When the value is not a biome
     */
    public static function parse(string $value): self
    {
        if ($value === self::NONE) {
            return new self(null);
        }

        return new self(Biome::tryFrom($value) ?? throw new InvalidArgumentException(sprintf(
            'Invalid biome "%s", expected "%s" or one of: %s',
            $value,
            self::NONE,
            implode(', ', array_column(Biome::cases(), 'value')),
        )));
    }

    /**
     * Errors and not classified maps never match
     */
    public function matches(MapResult $result): bool
    {
        return $result->classification !== null && $result->classification->biome === $this->biome;
    }
}
