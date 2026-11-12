<?php

declare(strict_types=1);

namespace MapClassifier\Tests;

use MapClassifier\OpenAI\BatchApiInterface;
use MapClassifier\OpenAI\BatchInfo;
use Override;

use function count;
use function file_get_contents;
use function file_put_contents;

/**
 * In memory implementation of the batch API
 */
final class FakeBatchApi implements BatchApiInterface
{
    /** @var array<string, string> Uploaded files content, indexed by file id */
    public array $files = [];

    /** @var array<string, BatchInfo> */
    public array $batches = [];

    /** @var array<string, array<string, string>> */
    public array $metadata = [];

    #[Override]
    public function uploadBatchFile(string $path): string
    {
        $id = 'file-' . (count($this->files) + 1);
        $this->files[$id] = (string) file_get_contents($path);

        return $id;
    }

    #[Override]
    public function createBatch(string $inputFileId, string $endpoint, array $metadata = []): BatchInfo
    {
        $id = 'batch_' . (count($this->batches) + 1);
        $this->metadata[$id] = $metadata + ['input_file_id' => $inputFileId, 'endpoint' => $endpoint];

        return $this->batches[$id] = new BatchInfo($id, 'validating');
    }

    #[Override]
    public function getBatch(string $batchId): BatchInfo
    {
        return $this->batches[$batchId];
    }

    #[Override]
    public function downloadFile(string $fileId, string $target): void
    {
        file_put_contents($target, $this->files[$fileId]);
    }

    /**
     * Simulate batch completion
     */
    public function complete(string $batchId, string $output, ?string $errors = null): void
    {
        $outputId = 'file-out-' . $batchId;
        $this->files[$outputId] = $output;
        $errorId = null;

        if ($errors !== null) {
            $errorId = 'file-err-' . $batchId;
            $this->files[$errorId] = $errors;
        }

        $this->batches[$batchId] = new BatchInfo($batchId, 'completed', $outputId, $errorId, 2, 2, 0);
    }
}
