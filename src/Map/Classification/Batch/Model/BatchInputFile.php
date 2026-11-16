<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Batch\Model;

/**
 * A generated JSONL file, ready to be submitted
 */
final readonly class BatchInputFile
{
    public function __construct(
        public string $path,
        /** @var array<int, string> Map versions indexed by map id */
        public array $maps,
        public int $size,
    ) {}
}
