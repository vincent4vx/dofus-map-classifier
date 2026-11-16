<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Command;

use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Classification\Service\SubAreaBiomeResolver;
use MapClassifier\Map\Command\MapSelectionInput;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Repository\MapFileRepository;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Repository\InteractiveObjectRepository;
use MapClassifier\Sprite\Repository\SpriteProfileRepository;
use MapClassifier\Sprite\Service\MapSpriteExtractor;
use MapClassifier\Sprite\Service\SpriteAnalyzer;
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
        private readonly MapSpriteExtractor $extractor,
        private readonly MapLocationRepository $locations,
        private readonly MapResultRepository $results,
        private readonly InteractiveObjectRepository $interactiveObjects,
        private readonly SpriteProfileRepository $profiles,
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
        $locations = $this->locations->tryAll(static fn (string $error) => $io->warning('Areas not available: ' . $error));
        $results = $this->results->all();

        $analyzer = new SpriteAnalyzer(
            $locations,
            $results,
            new SubAreaBiomeResolver()->resolve($locations, $results),
            (float) $input->getOption('min-ratio'),
            $this->interactiveObjects->tryByGfx(static fn (string $error) => $io->warning('Interactive objects not available: ' . $error)),
        );

        $analyzed = 0;
        $errors = [];
        $progress = $io->createProgressBar(count($maps));

        foreach ($maps as $map) {
            try {
                $analyzer->add($map->id, $this->extractor->extract($map));
                ++$analyzed;
            } catch (RenderException $e) {
                $errors[$e->error->value] = ($errors[$e->error->value] ?? 0) + 1;
            }

            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        $profiles = $analyzer->profiles();
        $this->profiles->save($profiles);

        foreach ($errors as $code => $count) {
            $io->text(sprintf('%d maps skipped: %s', $count, $code));
        }

        $io->success(sprintf(
            '%d maps analyzed: %d ground sprites and %d object sprites written to %s',
            $analyzed,
            count($profiles[SpriteSet::Grounds->value] ?? []),
            count($profiles[SpriteSet::Objects->value] ?? []),
            $this->profiles->file,
        ));

        return self::SUCCESS;
    }
}
