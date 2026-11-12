<?php

declare(strict_types=1);

namespace MapClassifier\Command;

use MapClassifier\Config;
use MapClassifier\Lang\MapLocationRepository;
use MapClassifier\Map\MapFileRepository;
use MapClassifier\Render\MapImageRenderer;
use MapClassifier\Render\RenderException;
use MapClassifier\Storage\MapResult;
use MapClassifier\Storage\ResultStorage;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_slice;
use function count;
use function is_file;
use function sprintf;

#[AsCommand('maps:render', 'Render client maps to PNG files')]
final class MapsRenderCommand extends Command
{
    public function __construct(
        private readonly MapFileRepository $maps,
        private readonly MapImageRenderer $renderer,
        private readonly MapLocationRepository $locations,
        private readonly ResultStorage $results,
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        MapSelection::configure($this, 'Map ids or ranges (e.g. 10000 100-200). All maps by default');
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
        $force = (bool) $input->getOption('force');
        $retry = (bool) $input->getOption('retry-errors');
        $todo = [];

        foreach (MapSelection::fromInput($input, $this->locations)->filter($this->maps->all()) as $map) {
            if (!$force && is_file($this->target($map->id))) {
                continue;
            }

            if (!$force && !$retry && ($this->results->get($map->id)?->error?->isRenderError() ?? false)) {
                continue;
            }

            $todo[] = $map;
        }

        if (($limit = $input->getOption('limit')) !== null) {
            $todo = array_slice($todo, 0, (int) $limit);
        }

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

            try {
                $this->renderer->renderTo($map, $this->target($map->id));
                ++$rendered;

                // Previous render error is now obsolete
                if ($this->results->get($map->id)?->error?->isRenderError()) {
                    $this->results->delete($map->id);
                }
            } catch (RenderException $e) {
                ++$failed;
                $this->results->save(MapResult::failure($map->id, $map->version, $e->errorCode, $e->getMessage()));
                $progress->clear();
                $io->writeln(sprintf('<comment>Map %d: %s (%s)</comment>', $map->id, $e->errorCode->value, $e->getMessage()), OutputInterface::VERBOSITY_VERBOSE);
                $progress->display();
            }

            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        if ($failed) {
            $io->warning(sprintf('%d maps rendered, %d failed (see errors in %s)', $rendered, $failed, $this->config->resultsDir));
        } else {
            $io->success(sprintf('%d maps rendered', $rendered));
        }

        return self::SUCCESS;
    }

    private function target(int $id): string
    {
        return $this->config->rendersDir . '/' . $id . '.png';
    }
}
