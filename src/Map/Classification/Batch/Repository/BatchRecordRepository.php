<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Batch\Repository;

use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Map\Classification\Batch\Model\BatchRecord;

use function array_filter;
use function array_values;
use function file_get_contents;
use function glob;
use function is_array;
use function json_decode;
use function usort;

use const JSON_THROW_ON_ERROR;

/**
 * Store submitted batches as "{dir}/{batch id}.json"
 */
final readonly class BatchRecordRepository
{
    public function __construct(
        private string $dir,
    ) {}

    public function save(BatchRecord $record): void
    {
        JsonFile::write($this->dir . '/' . $record->id . '.json', $record->toArray());
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
