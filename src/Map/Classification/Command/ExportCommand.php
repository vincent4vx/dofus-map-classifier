<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Command;

use InvalidArgumentException;
use MapClassifier\Infrastructure\Export\ExportFormat;
use MapClassifier\Infrastructure\Export\ExportFormatOption;
use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Classification\Model\MapExport;
use MapClassifier\Map\Classification\Service\MapExporter;
use MapClassifier\Map\Command\MapSelectionInput;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_keys;
use function array_map;
use function array_values;
use function count;
use function implode;
use function sprintf;

#[AsCommand('export', 'Export the maps results with their location: JSON data and HTML gallery for visual validation')]
final class ExportCommand extends Command
{
    public function __construct(
        private readonly MapExporter $exporter,
        private readonly MapLocationRepository $locations,
        /** @var list<ExportFormat<MapExport>> */
        private readonly array $formats,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        MapSelectionInput::configure($this, 'Map ids or ranges. All results by default');
        ExportFormatOption::configure($this, $this->formats);
        $this
            ->addOption('biome', 'b', InputOption::VALUE_REQUIRED, 'Only export classified maps of this biome ("none" for maps without biome)')
            ->addOption('only-ok', null, InputOption::VALUE_NONE, 'Exclude maps in error')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $formats = ExportFormatOption::selected($input, $this->formats);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return self::INVALID;
        }

        $biome = $input->getOption('biome');
        $locations = $this->locations->tryAll(static fn (string $error) => $io->warning('Locations not exported: ' . $error));
        $export = $this->exporter->export(
            $locations,
            MapSelectionInput::selection($input, $this->locations),
            $biome !== null ? (string) $biome : null,
            (bool) $input->getOption('only-ok'),
        );

        if ($export->subAreas) {
            $io->text(sprintf('%d / %d sub areas with a biome', $export->resolvedSubAreas(), count($export->subAreas)));
        }

        $io->table(['Biome', 'Maps'], array_map(null, array_keys($export->biomes), array_values($export->biomes)));

        foreach ($formats as $file => $format) {
            $io->text(sprintf('%s: %s', $format->name(), implode(', ', $format->write($export, $file))));
        }

        $io->success(sprintf('%d maps exported (%d in error)', count($export->maps), $export->errors));

        return self::SUCCESS;
    }
}
