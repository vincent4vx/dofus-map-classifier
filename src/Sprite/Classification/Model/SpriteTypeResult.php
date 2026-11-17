<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use MapClassifier\Sprite\Model\SpriteSet;
use Override;

use function is_int;
use function is_string;
use function str_starts_with;
use function substr;

/**
 * Type classification result of a sprite: either a classification, or an error
 *
 * JSON (sprite_types/{set}/{id}.json): {"set", "id", "status": "ok" | "error", "error", "error_message", ...type fields, "model", "classified_at"}
 * Type fields are null (and keywords empty) on error
 */
final class SpriteTypeResult implements JsonSerializable
{
    public function __construct(
        public readonly SpriteSet $set,
        public readonly int $id,
        public readonly ?SpriteType $classification = null,
        public readonly ?SpriteTypeError $error = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $model = null,
        public readonly ?DateTimeImmutable $classifiedAt = null,
    ) {}

    public bool $ok {
        get => $this->classification !== null && $this->error === null;
    }

    public static function success(SpriteSet $set, int $id, SpriteType $classification, string $model): self
    {
        return new self($set, $id, $classification, model: $model, classifiedAt: new DateTimeImmutable());
    }

    public static function failure(SpriteSet $set, int $id, SpriteTypeError $error, ?string $message = null, ?string $model = null): self
    {
        return new self($set, $id, error: $error, errorMessage: $message, model: $model, classifiedAt: new DateTimeImmutable());
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'set' => $this->set->value,
            'id' => $this->id,
            'status' => $this->ok ? 'ok' : 'error',
            'error' => $this->error?->value,
            'error_message' => $this->errorMessage,
            ...($this->classification?->jsonSerialize() ?? ['category' => null, 'name' => null, 'keywords' => [], 'description' => null, 'transition' => null]),
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
        $error = null;
        $message = is_string($data['error_message'] ?? null) ? $data['error_message'] : null;

        if (($data['status'] ?? null) === 'ok') {
            try {
                $classification = SpriteType::fromArray($data);
            } catch (InvalidArgumentException $e) {
                // Invalid stored data: considered as not classified
                [$error, $message] = [SpriteTypeError::ClassificationFailed, 'Invalid stored type: ' . $e->getMessage()];
            }
        } elseif (is_string($data['error'] ?? null)) {
            $error = SpriteTypeError::tryFrom($data['error']);

            // Results stored before the error codes: the error was only a message
            if ($error === null) {
                [$error, $message] = self::legacyError($data['error']);
            }
        } else {
            $error = SpriteTypeError::ClassificationFailed;
        }

        return new self(
            set: SpriteSet::tryFrom((string) ($data['set'] ?? '')) ?? throw new InvalidArgumentException('Invalid sprite set'),
            id: is_int($data['id'] ?? null) ? $data['id'] : 0,
            classification: $classification,
            error: $error,
            errorMessage: $message,
            model: is_string($data['model'] ?? null) ? $data['model'] : null,
            classifiedAt: is_string($data['classified_at'] ?? null) ? new DateTimeImmutable($data['classified_at']) : null,
        );
    }

    /**
     * @return array{SpriteTypeError, string|null} Error code and message of an error stored as a message
     */
    private static function legacyError(string $error): array
    {
        return match (true) {
            $error === 'Sprite image not exported' => [SpriteTypeError::ImageNotExported, null],
            str_starts_with($error, 'Refused: ') => [SpriteTypeError::Refused, substr($error, 9)],
            default => [SpriteTypeError::ClassificationFailed, $error],
        };
    }
}
