<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\Export;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use function array_diff;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function is_string;
use function sprintf;

/**
 * "--format" and "--output" options of the export commands
 */
final class ExportFormatOption
{
    /**
     * @template T of object
     *
     * @param list<ExportFormat<T>> $formats Available formats
     */
    public static function configure(Command $command, array $formats): void
    {
        $command
            ->addOption('format', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, sprintf(
                'Export formats (%s). All formats by default',
                implode(', ', array_map(static fn (ExportFormat $format) => $format->name() . ': ' . $format->defaultOutput(), $formats)),
            ))
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file, only with a single format')
        ;
    }

    /**
     * Selected formats, with their output file
     *
     * @template T of object
     *
     * @param list<ExportFormat<T>> $formats Available formats
     *
     * @return array<string, ExportFormat<T>> Formats indexed by output file
     *
     * @throws InvalidArgumentException When a format is unknown, or an output is given for several formats
     */
    public static function selected(InputInterface $input, array $formats): array
    {
        $byName = [];

        foreach ($formats as $format) {
            $byName[$format->name()] = $format;
        }

        /** @var list<string> $names */
        $names = $input->getOption('format') ?: array_keys($byName);

        if ($unknown = array_diff($names, array_keys($byName))) {
            throw new InvalidArgumentException(sprintf('Unknown format "%s", available: %s', implode('", "', $unknown), implode(', ', array_keys($byName))));
        }

        $output = $input->getOption('output');

        if (is_string($output) && count($names) !== 1) {
            throw new InvalidArgumentException('The --output option requires a single --format');
        }

        $selected = [];

        foreach ($names as $name) {
            $selected[is_string($output) ? $output : $byName[$name]->defaultOutput()] = $byName[$name];
        }

        return $selected;
    }
}
