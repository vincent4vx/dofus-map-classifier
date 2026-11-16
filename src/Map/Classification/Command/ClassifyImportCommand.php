<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Command;

use MapClassifier\Map\Classification\Batch\Service\BatchService;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function implode;
use function sprintf;

#[AsCommand('classify:import', 'Import the results of terminated batches')]
final class ClassifyImportCommand extends Command
{
    public function __construct(
        private readonly BatchService $service,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $service = $this->service;
        $imported = 0;
        $waiting = 0;

        foreach ($service->refresh() as $record) {
            if ($record->imported) {
                continue;
            }

            if (!$record->info->terminal) {
                ++$waiting;
                continue;
            }

            $report = $service->import($record);
            ++$imported;

            $io->text(sprintf(
                '%s (%s): %d classified, %d errors, %d without result',
                $record->id,
                $record->info->status,
                $report->ok,
                $report->errors,
                count($report->missing),
            ));

            if ($report->missing && $output->isVerbose()) {
                $io->text('  Maps without result (will be submitted again): ' . implode(', ', $report->missing));
            }
        }

        $io->success(sprintf('%d batch(es) imported, %d still running', $imported, $waiting));

        return self::SUCCESS;
    }
}
