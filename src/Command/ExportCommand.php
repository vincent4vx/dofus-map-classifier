<?php

declare(strict_types=1);

namespace MapClassifier\Command;

use MapClassifier\Config;
use MapClassifier\Lang\MapLocationRepository;
use MapClassifier\Storage\ResultStorage;
use MapClassifier\SubArea\SubAreaBiome;
use MapClassifier\SubArea\SubAreaBiomeResolver;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_filter;
use function array_keys;
use function array_map;
use function array_slice;
use function array_values;
use function arsort;
use function count;
use function file_put_contents;
use function sprintf;

#[AsCommand('export', 'Merge all map results into a single JSON file indexed by map id')]
final class ExportCommand extends Command
{
    public function __construct(
        private readonly ResultStorage $results,
        private readonly MapLocationRepository $locations,
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file', $this->config->varDir . '/maps.json')
            ->addOption('sub-areas-output', null, InputOption::VALUE_REQUIRED, 'Output file of sub areas biomes', $this->config->varDir . '/sub_areas.json')
            ->addOption('only-ok', null, InputOption::VALUE_NONE, 'Exclude maps in error')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $onlyOk = (bool) $input->getOption('only-ok');
        $data = [];
        $biomes = [];
        $errors = 0;
        $locations = $this->locations->tryAll(static fn (string $error) => $io->warning('Locations not exported: ' . $error));
        $results = $this->results->all();
        $subAreas = new SubAreaBiomeResolver()->resolve($locations, $results);

        foreach ($results as $id => $result) {
            if (!$result->ok) {
                ++$errors;

                if ($onlyOk) {
                    continue;
                }
            } else {
                $biome = $result->classification->biome->value ?? '(none)';
                $biomes[$biome] = ($biomes[$biome] ?? 0) + 1;
            }

            $row = $result->toArray();
            $location = ($locations[$id] ?? null)?->toArray();

            if ($location !== null) {
                $location['sub_area']['biome'] = $subAreas[$locations[$id]->subAreaId]->biome?->value;
            }

            // Insert location after the map id and version
            $data[(string) $id] = array_slice($row, 0, 2, true) + ['location' => $location] + $row;
        }

        $file = (string) $input->getOption('output');
        // Force an object, even when empty
        file_put_contents($file, ResultStorage::encode((object) $data) . "\n");

        if ($locations) {
            $subAreasFile = (string) $input->getOption('sub-areas-output');
            file_put_contents($subAreasFile, ResultStorage::encode((object) array_map(
                static fn (SubAreaBiome $subArea) => $subArea->toArray(),
                $subAreas,
            )) . "\n");
            $resolved = count(array_filter($subAreas, static fn (SubAreaBiome $subArea) => $subArea->biome !== null));
            $io->text(sprintf('%d / %d sub areas with a biome, written to %s', $resolved, count($subAreas), $subAreasFile));
        }

        arsort($biomes);
        $io->table(['Biome', 'Maps'], array_map(null, array_keys($biomes), array_values($biomes)));
        $io->success(sprintf('%d maps exported to %s (%d in error)', count($data), $file, $errors));

        return self::SUCCESS;
    }
}
