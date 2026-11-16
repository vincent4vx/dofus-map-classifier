<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model;

use InvalidArgumentException;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function is_array;
use function is_string;
use function mb_strtolower;
use function trim;

/**
 * Type of a sprite, returned by the model
 */
final readonly class ObjectTypeClassification
{
    public function __construct(
        public ObjectCategory $category,
        /** Short free name of the object, e.g. "pine tree" */
        public string $name,
        /** @var list<string> Lowercase search keywords */
        public array $keywords,
        public string $description,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $category = is_string($data['category'] ?? null) ? ObjectCategory::tryFrom($data['category']) : null;

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

        $keywords = array_values(array_unique(array_filter(
            array_map(static fn (string $keyword) => mb_strtolower(trim($keyword)), $data['keywords']),
            static fn (string $keyword) => $keyword !== '',
        )));

        return new self($category, mb_strtolower(trim($data['name'])), $keywords, trim($data['description']));
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

        return new self($this->category, $name, $keywords, $this->description);
    }

    /**
     * @return array{category: string, name: string, keywords: list<string>, description: string}
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category->value,
            'name' => $this->name,
            'keywords' => $this->keywords,
            'description' => $this->description,
        ];
    }
}
