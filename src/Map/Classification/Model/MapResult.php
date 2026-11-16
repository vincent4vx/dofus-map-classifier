<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Model;

use DateTimeImmutable;
use DateTimeInterface;

use function array_merge;
use function is_int;
use function is_string;

/**
 * Processing result of a map: either a classification, or an error
 */
final class MapResult
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $version,
        public readonly ?MapClassification $classification = null,
        public readonly ?ErrorCode $error = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $model = null,
        public readonly ?DateTimeImmutable $classifiedAt = null,
    ) {}

    public bool $ok {
        get => $this->error === null && $this->classification !== null;
    }

    public static function success(int $id, ?string $version, MapClassification $classification, string $model): self
    {
        return new self($id, $version, $classification, model: $model, classifiedAt: new DateTimeImmutable());
    }

    public static function failure(int $id, ?string $version, ErrorCode $error, ?string $message = null, ?string $model = null): self
    {
        return new self($id, $version, error: $error, errorMessage: $message, model: $model, classifiedAt: new DateTimeImmutable());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $classification = $this->classification?->toArray() ?? [
            'biome' => null,
            'biome_other' => null,
            'indoor' => null,
            'location_type' => null,
            'tags' => [],
            'description' => null,
        ];

        return array_merge(
            [
                'id' => $this->id,
                'version' => $this->version,
                'status' => $this->ok ? 'ok' : 'error',
                'error' => $this->error?->value,
                'error_message' => $this->errorMessage,
            ],
            $classification,
            [
                'model' => $this->model,
                'classified_at' => $this->classifiedAt?->format(DateTimeInterface::ATOM),
            ],
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $error = is_string($data['error'] ?? null) ? ErrorCode::tryFrom($data['error']) : null;

        return new self(
            id: is_int($data['id'] ?? null) ? $data['id'] : 0,
            version: is_string($data['version'] ?? null) ? $data['version'] : null,
            classification: ($data['status'] ?? null) === 'ok' ? MapClassification::fromArray($data) : null,
            error: $error,
            errorMessage: is_string($data['error_message'] ?? null) ? $data['error_message'] : null,
            model: is_string($data['model'] ?? null) ? $data['model'] : null,
            classifiedAt: is_string($data['classified_at'] ?? null) ? new DateTimeImmutable($data['classified_at']) : null,
        );
    }
}
