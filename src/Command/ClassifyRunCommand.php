<?php

declare(strict_types=1);

namespace MapClassifier\Command;

use MapClassifier\Batch\ImageEncoder;
use MapClassifier\Kernel;
use MapClassifier\Storage\MapResult;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function max;
use function sprintf;

#[AsCommand('classify:run', 'Classify rendered maps synchronously, by calling the OpenAI Responses API directly')]
final class ClassifyRunCommand extends Command
{
    public function __construct(
        private readonly Kernel $kernel,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('ids', InputArgument::IS_ARRAY, 'Map ids or ranges (e.g. 10000 100-200). All rendered maps by default')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of maps to classify')
            ->addOption('concurrency', 'c', InputOption::VALUE_REQUIRED, 'Number of parallel requests', '5')
            ->addOption('retry-errors', null, InputOption::VALUE_NONE, 'Classify again maps with a classification error')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Classify again already classified maps')
            ->addOption('include-pending', null, InputOption::VALUE_NONE, 'Also classify maps which are part of a running batch')
            ->addOption('image-format', null, InputOption::VALUE_REQUIRED, 'Image format sent to the model (jpeg or png)', ImageEncoder::JPEG)
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $todo = $this->kernel->mapsToClassify->select(
            MapSelection::parse($input->getArgument('ids')),
            force: (bool) $input->getOption('force'),
            retryErrors: (bool) $input->getOption('retry-errors'),
            includePending: (bool) $input->getOption('include-pending'),
            limit: $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null,
        );

        if (!$todo) {
            $io->success('Nothing to classify');

            return self::SUCCESS;
        }

        $io->text(sprintf('Classifying %d maps (model: %s)', count($todo), $this->kernel->config->openAiModel));

        $ok = 0;
        $errors = 0;
        $progress = $io->createProgressBar(count($todo));
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%');
        $progress->setMessage('');
        $progress->start();

        $this->kernel->syncClassifier((string) $input->getOption('image-format'))->classify(
            $todo,
            max(1, (int) $input->getOption('concurrency')),
            static function (MapResult $result) use ($io, $output, $progress, &$ok, &$errors) {
                if ($result->ok) {
                    ++$ok;
                    $message = sprintf('map %d: %s', $result->id, $result->classification->biome->value);
                } else {
                    ++$errors;
                    $message = sprintf('<comment>map %d: %s (%s)</comment>', $result->id, $result->error?->value, $result->errorMessage);
                }

                if ($output->isVerbose() || !$result->ok) {
                    $progress->clear();
                    $io->writeln($message);
                    $progress->display();
                }

                $progress->setMessage('map ' . $result->id);
                $progress->advance();
            },
        );

        $progress->finish();
        $io->newLine(2);

        $errors
            ? $io->warning(sprintf('%d maps classified, %d errors', $ok, $errors))
            : $io->success(sprintf('%d maps classified', $ok));

        return self::SUCCESS;
    }
}
