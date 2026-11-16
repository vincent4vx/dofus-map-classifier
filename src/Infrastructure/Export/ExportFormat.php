<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\Export;

/**
 * Format of an export (JSON data, HTML page…)
 *
 * @template T of object Exported data
 */
interface ExportFormat
{
    /**
     * Name of the format, used by the "--format" option
     */
    public function name(): string;

    /**
     * File written when no output is given
     */
    public function defaultOutput(): string;

    /**
     * Write the export to the output file
     *
     * @param T $export
     *
     * @return list<string> Written files
     */
    public function write(object $export, string $output): array;
}
