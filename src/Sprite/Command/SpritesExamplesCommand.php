<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Command;

use MapClassifier\Sprite\Repository\SpriteProfileRepository;
use MapClassifier\Sprite\Service\SpriteExampleRenderer;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function sprintf;

#[AsCommand('sprites:examples', 'Render an example map of each sprite, with a magenta frame around the sprite, sent to the model with the sprite image')]
final class SpritesExamplesCommand extends Command
{
    public function __construct(
        private readonly SpriteExampleRenderer $renderer,
        private readonly SpriteProfileRepository $profiles,
        private readonly string $examplesDir,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Render again already rendered examples');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $profiles = $this->profiles->all();
        } catch (RuntimeException $e) {
            $io->error($e->getMessage() . ': run sprites:analyze first');

            return self::FAILURE;
        }

        $withExample = [];

        foreach ($profiles as $profile) {
            if ($profile->example !== null) {
                $withExample[] = $profile;
            }
        }

        $progress = $io->createProgressBar(count($withExample));
        $progress->setFormat(' %current%/%max% [%bar%] %elapsed:6s%');
        $report = $this->renderer->render($withExample, (bool) $input->getOption('force'), static fn () => $progress->advance());
        $progress->finish();
        $io->newLine(2);

        foreach ($output->isVerbose() ? $report['failed'] : [] as $sprite => $error) {
            $io->text(sprintf('  <comment>%s: %s</comment>', $sprite, $error));
        }

        $io->success(sprintf(
            '%d examples rendered, %d already rendered, %d failed, in %s',
            $report['rendered'],
            $report['skipped'],
            count($report['failed']),
            $this->examplesDir,
        ));

        return self::SUCCESS;
    }
}
