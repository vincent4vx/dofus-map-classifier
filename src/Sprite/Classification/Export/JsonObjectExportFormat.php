<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Export;

use MapClassifier\Infrastructure\Export\ExportFormat;
use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Sprite\Classification\Model\Export\ObjectExport;
use Override;

/**
 * JSON file of the export (objects.json), see {@see ObjectExport}
 *
 * @implements ExportFormat<ObjectExport>
 */
final readonly class JsonObjectExportFormat implements ExportFormat
{
    public function __construct(
        private string $defaultOutput,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'json';
    }

    #[Override]
    public function defaultOutput(): string
    {
        return $this->defaultOutput;
    }

    #[Override]
    public function write(object $export, string $output): array
    {
        JsonFile::write($output, $export);

        return [$output];
    }
}
