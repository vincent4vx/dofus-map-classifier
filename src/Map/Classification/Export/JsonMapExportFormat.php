<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Export;

use MapClassifier\Infrastructure\Export\ExportFormat;
use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Map\Classification\Model\Export\ExportedMap;
use MapClassifier\Map\Classification\Model\MapExport;
use MapClassifier\Map\Classification\Model\SubAreaBiome;
use Override;

use function dirname;

/**
 * JSON files of the export:
 * - the output file (maps.json): {map id: {@see ExportedMap}}
 * - "sub_areas.json" in the same directory, when locations are available: {sub area id: {@see SubAreaBiome}}
 *
 * @implements ExportFormat<MapExport>
 */
final readonly class JsonMapExportFormat implements ExportFormat
{
    public const string SUB_AREAS_FILE = 'sub_areas.json';

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
        // Force objects, even when empty
        JsonFile::write($output, (object) $export->maps);

        if (!$export->subAreas) {
            return [$output];
        }

        $subAreasFile = dirname($output) . '/' . self::SUB_AREAS_FILE;
        JsonFile::write($subAreasFile, (object) $export->subAreas);

        return [$output, $subAreasFile];
    }
}
