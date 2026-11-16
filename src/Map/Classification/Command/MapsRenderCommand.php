<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Command;

use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Classification\Service\MapRenderService;
use MapClassifier\Map\Command\MapSelectionInput;
use MapClassifier\Map\Repository\MapFileRepository;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function sprintf;

#[AsCommand('maps:render', 'Render client maps to PNG files')]
final class MapsRenderCommand extends Command
{
    public function __construct(
        private readonly MapFileRepository $maps,
        private readonly MapLocationRepository $locations,
        private readonly MapRenderService $service,
        private readonly string $resultsDir,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        MapSelectionInput::configure($this, 'Map ids or ranges (e.g. 10000 100-200). All maps by default');
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Render again already rendered maps')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of maps to render')
            ->addOption('retry-errors', null, InputOption::VALUE_NONE, 'Retry maps previously failed to render')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $todo = $this->service->select(
            MapSelectionInput::selection($input, $this->locations)->filter($this->maps->all()),
            force: (bool) $input->getOption('force'),
            retryErrors: (bool) $input->getOption('retry-errors'),
            limit: $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null,
        );

        if (!$todo) {
            $io->success('Nothing to render');

            return self::SUCCESS;
        }

        $rendered = 0;
        $failed = 0;
        $progress = $io->createProgressBar(count($todo));
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%');
        $progress->setMessage('');

        foreach ($todo as $map) {
            $progress->setMessage('map ' . $map->id);

            $error = $this->service->render($map);

            if ($error === null) {
                ++$rendered;
            } else {
                ++$failed;
                $progress->clear();
                $io->writeln(sprintf('<comment>Map %d: %s (%s)</comment>', $map->id, $error->error->value, $error->getMessage()), OutputInterface::VERBOSITY_VERBOSE);
                $progress->display();
            }

            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        if ($failed) {
            $io->warning(sprintf('%d maps rendered, %d failed (see errors in %s)', $rendered, $failed, $this->resultsDir));
        } else {
            $io->success(sprintf('%d maps rendered', $rendered));
        }

        return self::SUCCESS;
    }
}
