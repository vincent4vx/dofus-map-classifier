<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Command;

use MapClassifier\Map\Classification\Batch\Model\BatchRecord;
use MapClassifier\Map\Classification\Batch\Repository\BatchRecordRepository;
use MapClassifier\Map\Classification\Batch\Service\BatchService;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_filter;
use function array_map;
use function count;
use function sprintf;

#[AsCommand('classify:status', 'Display the state of submitted batches')]
final class ClassifyStatusCommand extends Command
{
    public function __construct(
        private readonly BatchRecordRepository $batches,
        private readonly BatchService $service,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Also display imported batches')
            ->addOption('no-refresh', null, InputOption::VALUE_NONE, 'Do not query the API, only display the local state')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $records = $input->getOption('no-refresh') ? $this->batches->all() : $this->service->refresh();

        if (!$input->getOption('all')) {
            $records = array_filter($records, static fn (BatchRecord $record) => !$record->imported);
        }

        if (!$records) {
            $io->success('No batch to display');

            return self::SUCCESS;
        }

        $io->table(
            ['Batch', 'Status', 'Maps', 'Completed', 'Failed', 'Submitted', 'Imported'],
            array_map(static fn (BatchRecord $record) => [
                $record->id,
                $record->info->status . ($record->info->errors ? "\n" . $record->info->errors : ''),
                count($record->maps),
                sprintf('%d/%d', $record->info->completed, $record->info->total),
                $record->info->failed,
                $record->submittedAt->format('Y-m-d H:i'),
                ($record->importedAt?->format('Y-m-d H:i') ?? '-') . ($record->discarded ? ' (discarded)' : ''),
            ], $records),
        );

        return self::SUCCESS;
    }
}
