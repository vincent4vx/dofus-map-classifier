<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Command;

use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Classification\Service\MapExporter;
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
use function sprintf;

#[AsCommand('export', 'Merge all map results into a single JSON file indexed by map id')]
final class ExportCommand extends Command
{
    public function __construct(
        private readonly MapExporter $exporter,
        private readonly MapLocationRepository $locations,
        private readonly string $defaultOutput,
        private readonly string $defaultSubAreasOutput,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file', $this->defaultOutput)
            ->addOption('sub-areas-output', null, InputOption::VALUE_REQUIRED, 'Output file of sub areas biomes', $this->defaultSubAreasOutput)
            ->addOption('only-ok', null, InputOption::VALUE_NONE, 'Exclude maps in error')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locations = $this->locations->tryAll(static fn (string $error) => $io->warning('Locations not exported: ' . $error));
        $export = $this->exporter->export($locations, (bool) $input->getOption('only-ok'));

        $file = (string) $input->getOption('output');
        // Force an object, even when empty
        JsonFile::write($file, (object) $export->maps);

        if ($export->subAreas) {
            $subAreasFile = (string) $input->getOption('sub-areas-output');
            JsonFile::write($subAreasFile, (object) $export->subAreas);
            $io->text(sprintf('%d / %d sub areas with a biome, written to %s', $export->resolvedSubAreas(), count($export->subAreas), $subAreasFile));
        }

        $io->table(['Biome', 'Maps'], array_map(null, array_keys($export->biomes), array_values($export->biomes)));
        $io->success(sprintf('%d maps exported to %s (%d in error)', count($export->maps), $file, $export->errors));

        return self::SUCCESS;
    }
}
