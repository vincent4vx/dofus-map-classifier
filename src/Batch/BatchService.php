<?php

declare(strict_types=1);

namespace MapClassifier\Batch;

use DateTimeImmutable;
use MapClassifier\Map\MapFile;
use MapClassifier\OpenAI\BatchApiInterface;
use MapClassifier\Storage\ResultStorage;
use RuntimeException;

use function basename;
use function fclose;
use function fgets;
use function is_dir;
use function mkdir;
use function fopen;
use function is_file;
use function trim;

/**
 * Submit maps classification batches to OpenAI, and import their results
 */
final readonly class BatchService
{
    public function __construct(
        private BatchApiInterface $api,
        private BatchFileBuilder $builder,
        private BatchRegistry $registry,
        private ResultStorage $results,
        private BatchResultParser $parser,
        private string $batchesDir,
        private string $rendersDir,
        private string $model,
    ) {}

    /**
     * Build batch input files for the given maps
     *
     * @param iterable<MapFile> $maps
     *
     * @return list<BatchInputFile>
     */
    public function build(iterable $maps): array
    {
        return $this->builder->build(
            $maps,
            fn (MapFile $map) => $this->rendersDir . '/' . $map->id . '.png',
            $this->batchesDir . '/input',
            (new DateTimeImmutable())->format('Ymd-His'),
        );
    }

    /**
     * Upload the input file and create the batch
     */
    public function submit(BatchInputFile $file): BatchRecord
    {
        $fileId = $this->api->uploadBatchFile($file->path);
        $info = $this->api->createBatch($fileId, BatchFileBuilder::ENDPOINT, [
            'project' => 'map-classifier',
            'input' => basename($file->path),
        ]);

        $record = new BatchRecord($info->id, $file->path, $this->model, $file->maps, $info, new DateTimeImmutable());
        $this->registry->save($record);

        return $record;
    }

    /**
     * Refresh the state of all batches not yet imported
     *
     * @return list<BatchRecord> All batches, with up to date state
     */
    public function refresh(): array
    {
        $records = [];

        foreach ($this->registry->all() as $record) {
            if (!$record->imported && !$record->info->terminal) {
                $record = $record->withInfo($this->api->getBatch($record->id));
                $this->registry->save($record);
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * Import results of a terminated batch
     */
    public function import(BatchRecord $record): ImportReport
    {
        if (!$record->info->terminal) {
            throw new RuntimeException('Batch ' . $record->id . ' is not terminated (status: ' . $record->info->status . ')');
        }

        $report = new ImportReport();
        $seen = [];

        foreach ([$record->info->outputFileId, $record->info->errorFileId] as $fileId) {
            if ($fileId === null) {
                continue;
            }

            $target = $this->batchesDir . '/output/' . $fileId . '.jsonl';

            if (!is_file($target)) {
                if (!is_dir($dir = $this->batchesDir . '/output') && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new RuntimeException('Cannot create directory ' . $dir);
                }

                $this->api->downloadFile($fileId, $target);
            }

            $handle = fopen($target, 'rb') ?: throw new RuntimeException('Cannot open ' . $target);

            try {
                while (($line = fgets($handle)) !== false) {
                    if (trim($line) === '') {
                        continue;
                    }

                    $result = $this->parser->parse($line, $record->model, $record->maps);

                    if ($result === null || !isset($record->maps[$result->id])) {
                        continue;
                    }

                    $this->results->save($result);
                    $seen[$result->id] = true;

                    if ($result->ok) {
                        ++$report->ok;
                    } else {
                        ++$report->errors;
                    }
                }
            } finally {
                fclose($handle);
            }
        }

        foreach ($record->maps as $id => $_) {
            if (!isset($seen[$id])) {
                $report->missing[] = $id;
            }
        }

        $this->registry->save($record->markImported());

        return $report;
    }
}
