<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Command;

use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Sprite\Classification\Service\ObjectExporter;
use MapClassifier\Sprite\Repository\ObjectProfileRepository;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

#[AsCommand('objects:export', 'Export the sprites profiles with their type into a single JSON file')]
final class ObjectsExportCommand extends Command
{
    public function __construct(
        private readonly ObjectProfileRepository $profiles,
        private readonly ObjectExporter $exporter,
        private readonly string $defaultOutput,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file', $this->defaultOutput);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $profiles = $this->profiles->all();
        } catch (RuntimeException $e) {
            $io->error($e->getMessage() . ': run objects:analyze first');

            return self::FAILURE;
        }

        $file = (string) $input->getOption('output');
        $export = $this->exporter->export($profiles);
        JsonFile::write($file, $export);

        $io->success(sprintf('%d sprites exported to %s (%d with a type)', $export->count(), $file, $export->classified()));

        return self::SUCCESS;
    }
}
