<?php

declare(strict_types=1);

namespace MapClassifier\Command;

use MapClassifier\Config;
use MapClassifier\Lang\MapLocationRepository;
use MapClassifier\Map\MapFileRepository;
use MapClassifier\Objects\MapObjectExtractor;
use MapClassifier\Objects\ObjectAnalyzer;
use MapClassifier\Objects\ObjectProfile;
use MapClassifier\Render\RenderException;
use MapClassifier\Storage\ResultStorage;
use MapClassifier\SubArea\SubAreaBiomeResolver;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_map;
use function count;
use function file_put_contents;
use function sprintf;

#[AsCommand('objects:analyze', 'Aggregate maps data (layer, areas, biome…) on each sprite used by the maps')]
final class ObjectsAnalyzeCommand extends Command
{
    public function __construct(
        private readonly MapFileRepository $maps,
        private readonly MapObjectExtractor $extractor,
        private readonly MapLocationRepository $locations,
        private readonly ResultStorage $results,
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        MapSelection::configure($this, 'Map ids or ranges to analyze. All maps by default');
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file', $this->config->varDir . '/objects.json')
            ->addOption('min-ratio', null, InputOption::VALUE_REQUIRED, 'Attribute values with a count lower than this ratio of the main value are ignored', '0.1')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $maps = MapSelection::fromInput($input, $this->locations)->filter($this->maps->all());
        $locations = $this->locations->tryAll(static fn (string $error) => $io->warning('Areas not available: ' . $error));
        $results = $this->results->all();

        $analyzer = new ObjectAnalyzer(
            $locations,
            $results,
            new SubAreaBiomeResolver()->resolve($locations, $results),
            (float) $input->getOption('min-ratio'),
        );

        $analyzed = 0;
        $errors = [];
        $progress = $io->createProgressBar(count($maps));

        foreach ($maps as $map) {
            try {
                $analyzer->add($map->id, $this->extractor->extract($map));
                ++$analyzed;
            } catch (RenderException $e) {
                $errors[$e->errorCode->value] = ($errors[$e->errorCode->value] ?? 0) + 1;
            }

            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        $profiles = $analyzer->profiles();
        $file = (string) $input->getOption('output');

        file_put_contents($file, ResultStorage::encode(array_map(
            static fn (array $sprites) => (object) array_map(static fn (ObjectProfile $profile) => $profile->toArray(), $sprites),
            $profiles,
        )) . "\n");

        foreach ($errors as $code => $count) {
            $io->text(sprintf('%d maps skipped: %s', $count, $code));
        }

        $io->success(sprintf(
            '%d maps analyzed: %d ground sprites and %d object sprites written to %s',
            $analyzed,
            count($profiles['grounds'] ?? []),
            count($profiles['objects'] ?? []),
            $file,
        ));

        return self::SUCCESS;
    }
}
