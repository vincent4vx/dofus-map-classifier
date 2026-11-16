<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Command;

use MapClassifier\Infrastructure\Filesystem\Path;
use MapClassifier\Sprite\Classification\Report\SpriteReport;
use MapClassifier\Sprite\Classification\Service\ObjectTypeResolver;
use MapClassifier\Sprite\Repository\ObjectProfileRepository;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function dirname;
use function file_put_contents;
use function sprintf;

/**
 * Generate an HTML page displaying sprites and their aggregated data, with filters
 */
#[AsCommand('objects:report', 'Generate an HTML gallery of sprites with their aggregated data and filters')]
final class ObjectsReportCommand extends Command
{
    public function __construct(
        private readonly ObjectProfileRepository $profiles,
        private readonly ObjectTypeResolver $types,
        private readonly string $spritesDir,
        private readonly string $defaultOutput,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file (must be in the var directory to resolve images)', $this->defaultOutput)
        ;
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
        // Types are resolved from the stored classifications, to display them even if objects:analyze was not run again
        file_put_contents($file, new SpriteReport()->render($profiles, $this->types, Path::relativeUrl(dirname($file), $this->spritesDir)));

        $io->success(sprintf('Report of %d sprites written to %s', count($profiles['grounds'] ?? []) + count($profiles['objects'] ?? []), $file));

        return self::SUCCESS;
    }
}
