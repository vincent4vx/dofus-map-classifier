<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Shore;

use InvalidArgumentException;
use JsonSerializable;
use Override;

use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_string;

/**
 * The role of a shore piece in the shores of the maps, as the model sees it on its example along the water (see {@see ShoreRolePrompt})
 *
 * JSON: {"kind": string, "shape": string, "water": [direction], "land": [direction], "continues": [direction], "description": string}
 */
final readonly class ShoreRole implements JsonSerializable
{
    /** What the piece draws */
    public const array KINDS = ['cliff', 'grass_edge', 'other'];

    /** Where the piece lies along the shore */
    public const array SHAPES = ['straight', 'outer_corner', 'inner_corner', 'end', 'other'];

    /** Screen directions: n is up */
    public const array DIRECTIONS = ['n', 'ne', 'e', 'se', 's', 'sw', 'w', 'nw'];

    public function __construct(
        public string $kind,
        public string $shape,
        /** @var list<string> Directions of the water around the piece */
        public array $water,
        /** @var list<string> Directions of the land around the piece */
        public array $land,
        /** @var list<string> Directions in which the shore goes on beyond the piece */
        public array $continues,
        public string $description,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidArgumentException When a value is invalid
     */
    public static function fromArray(array $data): self
    {
        $value = static function (string $field, array $allowed) use ($data): string {
            $value = $data[$field] ?? null;

            return is_string($value) && in_array($value, $allowed, true) ? $value : throw new InvalidArgumentException('Invalid ' . $field);
        };
        $directions = static function (string $field) use ($data): array {
            $values = is_array($data[$field] ?? null) ? $data[$field] : throw new InvalidArgumentException('Invalid ' . $field);

            return array_values(array_map(static fn (mixed $direction) => is_string($direction) && in_array($direction, self::DIRECTIONS, true)
                ? $direction : throw new InvalidArgumentException('Invalid direction in ' . $field), $values));
        };

        return new self(
            $value('kind', self::KINDS),
            $value('shape', self::SHAPES),
            $directions('water'),
            $directions('land'),
            $directions('continues'),
            is_string($data['description'] ?? null) ? $data['description'] : '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'kind' => $this->kind,
            'shape' => $this->shape,
            'water' => $this->water,
            'land' => $this->land,
            'continues' => $this->continues,
            'description' => $this->description,
        ];
    }
}
