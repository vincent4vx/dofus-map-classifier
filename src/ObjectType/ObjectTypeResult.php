<?php

declare(strict_types=1);

namespace MapClassifier\ObjectType;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use MapClassifier\Objects\SpriteSet;

use function is_array;
use function is_int;
use function is_string;

/**
 * Type classification result of a sprite: either a classification, or an error
 */
final class ObjectTypeResult
{
    public function __construct(
        public readonly SpriteSet $set,
        public readonly int $id,
        public readonly ?ObjectTypeClassification $classification = null,
        public readonly ?string $error = null,
        public readonly ?string $model = null,
        public readonly ?DateTimeImmutable $classifiedAt = null,
    ) {}

    public bool $ok {
        get => $this->classification !== null && $this->error === null;
    }

    public static function success(SpriteSet $set, int $id, ObjectTypeClassification $classification, string $model): self
    {
        return new self($set, $id, $classification, model: $model, classifiedAt: new DateTimeImmutable());
    }

    public static function failure(SpriteSet $set, int $id, string $error, ?string $model = null): self
    {
        return new self($set, $id, error: $error, model: $model, classifiedAt: new DateTimeImmutable());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'set' => $this->set->value,
            'id' => $this->id,
            'status' => $this->ok ? 'ok' : 'error',
            'error' => $this->error,
            ...($this->classification?->toArray() ?? ['category' => null, 'name' => null, 'keywords' => [], 'description' => null]),
            'model' => $this->model,
            'classified_at' => $this->classifiedAt?->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $classification = null;

        if (($data['status'] ?? null) === 'ok') {
            try {
                $classification = ObjectTypeClassification::fromArray($data);
            } catch (InvalidArgumentException) {
                // Invalid stored data: considered as not classified
            }
        }

        return new self(
            set: SpriteSet::from((string) ($data['set'] ?? '')),
            id: is_int($data['id'] ?? null) ? $data['id'] : 0,
            classification: $classification,
            error: is_string($data['error'] ?? null) ? $data['error'] : ($classification === null ? 'invalid' : null),
            model: is_string($data['model'] ?? null) ? $data['model'] : null,
            classifiedAt: is_string($data['classified_at'] ?? null) ? new DateTimeImmutable($data['classified_at']) : null,
        );
    }
}
