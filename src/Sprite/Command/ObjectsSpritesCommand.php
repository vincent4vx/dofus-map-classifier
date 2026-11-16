<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Command;

use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Repository\ObjectProfileRepository;
use MapClassifier\Sprite\Service\SpriteExporter;
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

#[AsCommand('objects:sprites', 'Render the ground and object sprites to the PNG files sent to the model')]
final class ObjectsSpritesCommand extends Command
{
    public function __construct(
        private readonly SpriteExporter $exporter,
        private readonly ObjectProfileRepository $profiles,
        private readonly string $spritesDir,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Export all sprites of the client, even unused ones (by default, only sprites of objects:analyze are exported)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Export again already exported sprites')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $used = null;

        if (!$input->getOption('all')) {
            try {
                $used = $this->profiles->all();
            } catch (RuntimeException $e) {
                $io->error($e->getMessage() . ': run objects:analyze first, or use --all');

                return self::FAILURE;
            }
        }

        foreach (SpriteSet::cases() as $set) {
            $only = $used === null ? null : ($used[$set->value] ?? []);
            $progress = $io->createProgressBar($only !== null ? count($only) : 0);
            $progress->setFormat(' ' . $set->value . ' %current%/%max% [%bar%] %elapsed:6s%');

            $report = $this->exporter->export(
                $set,
                $only,
                (bool) $input->getOption('force'),
                static fn () => $progress->advance(),
            );

            $progress->finish();
            $io->newLine();
            $io->text(sprintf('%s: %d exported, %d already exported, %d failed', $set->value, $report['exported'], $report['skipped'], count($report['failed'])));

            if ($output->isVerbose()) {
                foreach ($report['failed'] as $id => $error) {
                    $io->text(sprintf('  <comment>%d: %s</comment>', $id, $error));
                }
            }
        }

        $io->success('Sprites exported to ' . $this->spritesDir);

        return self::SUCCESS;
    }
}
