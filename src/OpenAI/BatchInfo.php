<?php

declare(strict_types=1);

namespace MapClassifier\OpenAI;

use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;

/**
 * State of a batch on OpenAI side
 */
final class BatchInfo
{
    public const array TERMINAL_STATUSES = ['completed', 'failed', 'expired', 'cancelled'];

    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $outputFileId = null,
        public readonly ?string $errorFileId = null,
        public readonly int $total = 0,
        public readonly int $completed = 0,
        public readonly int $failed = 0,
        /** Batch level errors (e.g. invalid input file) */
        public readonly ?string $errors = null,
    ) {}

    public bool $terminal {
        get => in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Create from the API batch object
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromApi(array $data): self
    {
        $counts = is_array($data['request_counts'] ?? null) ? $data['request_counts'] : [];
        $errors = null;

        if (is_array($data['errors']['data'] ?? null)) {
            $messages = [];

            foreach ($data['errors']['data'] as $error) {
                if (is_array($error) && is_string($error['message'] ?? null)) {
                    $messages[] = (is_int($error['line'] ?? null) ? 'line ' . $error['line'] . ': ' : '') . $error['message'];
                }
            }

            $errors = $messages ? implode("\n", $messages) : null;
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            status: (string) ($data['status'] ?? 'unknown'),
            outputFileId: is_string($data['output_file_id'] ?? null) ? $data['output_file_id'] : null,
            errorFileId: is_string($data['error_file_id'] ?? null) ? $data['error_file_id'] : null,
            total: is_int($counts['total'] ?? null) ? $counts['total'] : 0,
            completed: is_int($counts['completed'] ?? null) ? $counts['completed'] : 0,
            failed: is_int($counts['failed'] ?? null) ? $counts['failed'] : 0,
            errors: $errors,
        );
    }
}
