<?php

declare(strict_types=1);

namespace MapClassifier\Command;

use MapClassifier\Config;
use MapClassifier\Storage\ResultStorage;
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
use function arsort;
use function count;
use function file_put_contents;
use function sprintf;

#[AsCommand('export', 'Merge all map results into a single JSON file indexed by map id')]
final class ExportCommand extends Command
{
    public function __construct(
        private readonly ResultStorage $results,
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file', $this->config->varDir . '/maps.json')
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

        foreach ($this->results->all() as $id => $result) {
            if (!$result->ok) {
                ++$errors;

                if ($onlyOk) {
                    continue;
                }
            } else {
                $biome = $result->classification->biome->value ?? '(none)';
                $biomes[$biome] = ($biomes[$biome] ?? 0) + 1;
            }

            $data[(string) $id] = $result->toArray();
        }

        $file = (string) $input->getOption('output');
        // Force an object, even when empty
        file_put_contents($file, ResultStorage::encode((object) $data) . "\n");

        arsort($biomes);
        $io->table(['Biome', 'Maps'], array_map(null, array_keys($biomes), array_values($biomes)));
        $io->success(sprintf('%d maps exported to %s (%d in error)', count($data), $file, $errors));

        return self::SUCCESS;
    }
}
