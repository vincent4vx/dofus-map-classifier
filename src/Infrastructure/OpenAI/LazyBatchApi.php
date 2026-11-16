<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\OpenAI;

use Closure;
use Override;

/**
 * Resolve the actual API client on first call
 */
final class LazyBatchApi implements BatchApiInterface
{
    private ?BatchApiInterface $api = null;

    public function __construct(
        /** @var Closure(): BatchApiInterface */
        private readonly Closure $factory,
    ) {}

    #[Override]
    public function uploadBatchFile(string $path): string
    {
        return $this->api()->uploadBatchFile($path);
    }

    #[Override]
    public function createBatch(string $inputFileId, string $endpoint, array $metadata = []): BatchInfo
    {
        return $this->api()->createBatch($inputFileId, $endpoint, $metadata);
    }

    #[Override]
    public function getBatch(string $batchId): BatchInfo
    {
        return $this->api()->getBatch($batchId);
    }

    #[Override]
    public function downloadFile(string $fileId, string $target): void
    {
        $this->api()->downloadFile($fileId, $target);
    }

    private function api(): BatchApiInterface
    {
        return $this->api ??= ($this->factory)();
    }
}
