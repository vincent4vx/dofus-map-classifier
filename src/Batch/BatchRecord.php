<?php

declare(strict_types=1);

namespace MapClassifier\Batch;

use DateTimeImmutable;
use DateTimeInterface;
use MapClassifier\OpenAI\BatchInfo;

use function is_array;
use function is_string;

/**
 * Local tracking of a submitted batch
 */
final class BatchRecord
{
    public function __construct(
        /** OpenAI batch id */
        public readonly string $id,
        public readonly string $inputFile,
        public readonly string $model,
        /** @var array<int, string> Map versions indexed by map id */
        public readonly array $maps,
        public readonly BatchInfo $info,
        public readonly DateTimeImmutable $submittedAt,
        public readonly ?DateTimeImmutable $importedAt = null,
    ) {}

    public bool $imported {
        get => $this->importedAt !== null;
    }

    public function withInfo(BatchInfo $info): self
    {
        return new self($this->id, $this->inputFile, $this->model, $this->maps, $info, $this->submittedAt, $this->importedAt);
    }

    public function markImported(): self
    {
        return new self($this->id, $this->inputFile, $this->model, $this->maps, $this->info, $this->submittedAt, new DateTimeImmutable());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'input_file' => $this->inputFile,
            'model' => $this->model,
            'status' => $this->info->status,
            'output_file_id' => $this->info->outputFileId,
            'error_file_id' => $this->info->errorFileId,
            'request_counts' => [
                'total' => $this->info->total,
                'completed' => $this->info->completed,
                'failed' => $this->info->failed,
            ],
            'errors' => $this->info->errors,
            'submitted_at' => $this->submittedAt->format(DateTimeInterface::ATOM),
            'imported_at' => $this->importedAt?->format(DateTimeInterface::ATOM),
            'maps' => $this->maps,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $maps = [];

        foreach (is_array($data['maps'] ?? null) ? $data['maps'] : [] as $id => $version) {
            $maps[(int) $id] = (string) $version;
        }

        return new self(
            id: (string) $data['id'],
            inputFile: (string) ($data['input_file'] ?? ''),
            model: (string) ($data['model'] ?? ''),
            maps: $maps,
            info: BatchInfo::fromApi([
                'id' => $data['id'],
                'status' => $data['status'] ?? 'unknown',
                'output_file_id' => $data['output_file_id'] ?? null,
                'error_file_id' => $data['error_file_id'] ?? null,
                'request_counts' => $data['request_counts'] ?? [],
                'errors' => is_string($data['errors'] ?? null) ? ['data' => [['message' => $data['errors']]]] : null,
            ]),
            submittedAt: new DateTimeImmutable(is_string($data['submitted_at'] ?? null) ? $data['submitted_at'] : 'now'),
            importedAt: is_string($data['imported_at'] ?? null) ? new DateTimeImmutable($data['imported_at']) : null,
        );
    }
}
