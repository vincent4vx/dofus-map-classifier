<?php

declare(strict_types=1);

namespace MapClassifier\Command;

use MapClassifier\Batch\BatchRegistry;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function sprintf;

/**
 * Discard batches: their results will never be imported, and their maps are no longer considered as pending
 * Use it for cancelled batches, or batches submitted with an obsolete prompt
 * Note: the batch is not cancelled on OpenAI side
 */
#[AsCommand('classify:discard', 'Discard batches: their results will never be imported')]
final class ClassifyDiscardCommand extends Command
{
    public function __construct(
        private readonly BatchRegistry $batches,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('batches', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Batch ids to discard');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ids = $input->getArgument('batches');
        $discarded = 0;

        foreach ($ids as $id) {
            $record = $this->batches->get($id);

            if ($record === null) {
                $io->warning('Batch ' . $id . ' not found');
                continue;
            }

            if ($record->imported) {
                $io->warning(sprintf('Batch %s is already %s', $id, $record->discarded ? 'discarded' : 'imported'));
                continue;
            }

            $this->batches->save($record->discard());
            ++$discarded;
            $io->text(sprintf('%s discarded (%d maps)', $id, count($record->maps)));
        }

        $io->success(sprintf('%d batch(es) discarded', $discarded));

        return $discarded === count($ids) ? self::SUCCESS : self::FAILURE;
    }
}
