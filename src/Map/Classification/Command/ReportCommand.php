<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Command;

use MapClassifier\Infrastructure\Filesystem\Path;
use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Report\MapReport;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Classification\Service\SubAreaBiomeResolver;
use MapClassifier\Map\Command\MapSelectionInput;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_filter;
use function count;
use function dirname;
use function file_put_contents;
use function sprintf;

/**
 * Generate an HTML page displaying renders and their classification, for visual validation
 */
#[AsCommand('report', 'Generate an HTML gallery of classified maps for visual validation')]
final class ReportCommand extends Command
{
    public function __construct(
        private readonly MapResultRepository $results,
        private readonly MapLocationRepository $locations,
        private readonly string $rendersDir,
        private readonly string $defaultOutput,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        MapSelectionInput::configure($this, 'Map ids or ranges. All results by default');
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file (must be in the var directory to resolve images)', $this->defaultOutput)
            ->addOption('biome', 'b', InputOption::VALUE_REQUIRED, 'Only display maps of this biome ("none" for maps without biome)')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $selection = MapSelectionInput::selection($input, $this->locations);
        $biome = $input->getOption('biome');
        $file = (string) $input->getOption('output');

        $results = array_filter(
            $this->results->all(),
            static fn (MapResult $result) => $selection->matches($result->id)
                && ($biome === null || ($result->classification !== null && ($result->classification->biome->value ?? 'none') === $biome)),
        );

        $locations = $this->locations->tryAll(static fn (string $error) => $io->warning('Locations not displayed: ' . $error));
        $subAreas = new SubAreaBiomeResolver()->resolve($locations, $this->results->all());

        file_put_contents($file, new MapReport()->render($results, $locations, $subAreas, Path::relativeUrl(dirname($file), $this->rendersDir)));
        $io->success(sprintf('Report of %d maps written to %s', count($results), $file));

        return self::SUCCESS;
    }
}
