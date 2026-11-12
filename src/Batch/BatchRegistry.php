<?php

declare(strict_types=1);

namespace MapClassifier\Batch;

use MapClassifier\Storage\ResultStorage;
use RuntimeException;

use function array_filter;
use function array_values;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function json_decode;
use function mkdir;
use function rename;
use function usort;

use const JSON_THROW_ON_ERROR;

/**
 * Store submitted batches as "{dir}/{batch id}.json"
 */
final readonly class BatchRegistry
{
    public function __construct(
        private string $dir,
    ) {}

    public function save(BatchRecord $record): void
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0755, true) && !is_dir($this->dir)) {
            throw new RuntimeException('Cannot create directory ' . $this->dir);
        }

        $file = $this->dir . '/' . $record->id . '.json';
        file_put_contents($file . '.tmp', ResultStorage::encode($record->toArray()) . "\n");
        rename($file . '.tmp', $file);
    }

    /**
     * @return list<BatchRecord> Sorted by submission date
     */
    public function all(): array
    {
        $records = [];

        foreach (glob($this->dir . '/batch_*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

            if (is_array($data)) {
                $records[] = BatchRecord::fromArray($data);
            }
        }

        usort($records, static fn (BatchRecord $a, BatchRecord $b) => $a->submittedAt <=> $b->submittedAt);

        return $records;
    }

    public function get(string $id): ?BatchRecord
    {
        foreach ($this->all() as $record) {
            if ($record->id === $id) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Batches not yet imported
     *
     * @return list<BatchRecord>
     */
    public function pending(): array
    {
        return array_values(array_filter($this->all(), static fn (BatchRecord $record) => !$record->imported));
    }

    /**
     * Ids of maps which are part of a batch not yet imported
     *
     * @return array<int, true>
     */
    public function pendingMapIds(): array
    {
        $ids = [];

        foreach ($this->pending() as $record) {
            foreach ($record->maps as $id => $_) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }
}
