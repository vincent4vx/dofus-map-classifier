<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Command;

use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Command\MapSelectionInput;
use MapClassifier\Map\Repository\MapFileRepository;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Repository\AreaProfileRepository;
use MapClassifier\Sprite\Repository\SpriteProfileRepository;
use MapClassifier\Sprite\Service\SpriteAnalysis;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function sprintf;

#[AsCommand('sprites:analyze', 'Aggregate maps data (layer, areas, biome…) on each sprite used by the maps')]
final class SpritesAnalyzeCommand extends Command
{
    public function __construct(
        private readonly MapFileRepository $maps,
        private readonly MapLocationRepository $locations,
        private readonly SpriteAnalysis $analysis,
        private readonly SpriteProfileRepository $profiles,
        private readonly AreaProfileRepository $areas,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        MapSelectionInput::configure($this, 'Map ids or ranges to analyze. All maps by default');
        $this
            ->addOption('min-ratio', null, InputOption::VALUE_REQUIRED, 'Attribute values with a count lower than this ratio of the main value are ignored', '0.1')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $maps = MapSelectionInput::selection($input, $this->locations)->filter($this->maps->all());
        $progress = $io->createProgressBar(count($maps));

        $report = $this->analysis->analyze(
            $maps,
            (float) $input->getOption('min-ratio'),
            static fn (string $warning) => $io->warning($warning),
            static fn () => $progress->advance(),
        );

        $progress->finish();
        $io->newLine(2);

        $profiles = $report->profiles;
        $this->profiles->save($profiles);
        $this->areas->saveAll($report->areas);

        foreach ($report->skippedMaps as $error => $count) {
            $io->text(sprintf('%d maps skipped: %s', $count, $error));
        }

        $io->success(sprintf(
            '%d maps analyzed: %d ground sprites and %d object sprites written to %s',
            $report->analyzedMaps,
            count($profiles->bySet(SpriteSet::Grounds)),
            count($profiles->bySet(SpriteSet::Objects)),
            $this->profiles->file,
        ));
        $io->text(sprintf('%d area profiles written to %s', count($report->areas), $this->areas->directory));

        return self::SUCCESS;
    }
}
