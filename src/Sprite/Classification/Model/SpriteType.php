<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model;

use InvalidArgumentException;
use JsonSerializable;
use Override;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function is_array;
use function is_bool;
use function is_string;
use function mb_strtolower;
use function trim;

/**
 * Type of a sprite, returned by the model
 *
 * JSON: {"category", "name", "keywords", "description", "transition", "usage"}, same as the model output schema
 */
final readonly class SpriteType implements JsonSerializable
{
    public function __construct(
        public SpriteCategory $category,
        /** Short free name of the object, e.g. "pine tree" */
        public string $name,
        /** @var list<string> Lowercase search keywords */
        public array $keywords,
        public string $description,
        /**
         * The sprite is a transition from an environment or material to another (e.g. water to grass, grass to sand)
         * Null for the types found before this field was asked to the model
         */
        public ?bool $transition = null,
        /** How the sprite is used on the maps, depending on its layer. Null for backgrounds, and for the types found before this field was asked */
        public ?SpriteUsage $usage = null,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $category = is_string($data['category'] ?? null) ? SpriteCategory::tryFrom($data['category']) : null;

        if ($category === null) {
            throw new InvalidArgumentException('Invalid category: ' . (is_string($data['category'] ?? null) ? $data['category'] : '?'));
        }

        if (!is_string($data['name'] ?? null) || trim($data['name']) === '') {
            throw new InvalidArgumentException('Invalid name');
        }

        if (!is_array($data['keywords'] ?? null)) {
            throw new InvalidArgumentException('Invalid keywords');
        }

        foreach ($data['keywords'] as $keyword) {
            if (!is_string($keyword)) {
                throw new InvalidArgumentException('Invalid keywords');
            }
        }

        if (!is_string($data['description'] ?? null)) {
            throw new InvalidArgumentException('Invalid description');
        }

        $transition = $data['transition'] ?? null;

        if ($transition !== null && !is_bool($transition)) {
            throw new InvalidArgumentException('Invalid transition');
        }

        $usage = $data['usage'] ?? null;

        if ($usage !== null && !($usage = is_string($usage) ? SpriteUsage::tryFrom($usage) : null)) {
            throw new InvalidArgumentException('Invalid usage');
        }

        $keywords = array_values(array_unique(array_filter(
            array_map(static fn (string $keyword) => mb_strtolower(trim($keyword)), $data['keywords']),
            static fn (string $keyword) => $keyword !== '',
        )));

        return new self($category, mb_strtolower(trim($data['name'])), $keywords, trim($data['description']), $transition, $usage);
    }

    /**
     * Replace the name, keeping the previous name and the given keywords as keywords
     */
    public function withName(string $name, string ...$keywords): self
    {
        $name = mb_strtolower(trim($name));
        $keywords = array_values(array_unique(array_filter(
            [...$this->keywords, ...array_map(static fn (string $keyword) => mb_strtolower(trim($keyword)), [$this->name, ...$keywords])],
            static fn (string $keyword) => $keyword !== '' && $keyword !== $name,
        )));

        return new self($this->category, $name, $keywords, $this->description, $this->transition, $this->usage);
    }

    /**
     * @return array{category: string, name: string, keywords: list<string>, description: string, transition: bool|null, usage: string|null}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'category' => $this->category->value,
            'name' => $this->name,
            'keywords' => $this->keywords,
            'description' => $this->description,
            'transition' => $this->transition,
            'usage' => $this->usage?->value,
        ];
    }
}
