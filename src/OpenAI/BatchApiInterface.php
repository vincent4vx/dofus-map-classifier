<?php

declare(strict_types=1);

namespace MapClassifier\OpenAI;

/**
 * Subset of the OpenAI Batch API used by the application
 */
interface BatchApiInterface
{
    /**
     * Upload a JSONL batch input file
     *
     * @return string The file id
     */
    public function uploadBatchFile(string $path): string;

    /**
     * Create a batch from an uploaded file
     *
     * @param array<string, string> $metadata
     */
    public function createBatch(string $inputFileId, string $endpoint, array $metadata = []): BatchInfo;

    public function getBatch(string $batchId): BatchInfo;

    /**
     * Download a file content to $target
     */
    public function downloadFile(string $fileId, string $target): void;
}
