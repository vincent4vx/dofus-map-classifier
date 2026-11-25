<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Command;

use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Repository\MapFileRepository;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Repository\SpriteProfileRepository;
use MapClassifier\Sprite\Service\ShoreExampleRenderer;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_fill_keys;
use function array_filter;
use function array_intersect_key;
use function array_keys;
use function count;
use function explode;
use function imagepng;
use function is_dir;
use function mkdir;
use function range;
use function sprintf;
use function str_contains;

#[AsCommand('shores:examples', 'Render an example of each shore piece along the water, with only the ground and the shore pieces')]
final class ShoresExamplesCommand extends Command
{
    public function __construct(
        private readonly MapFileRepository $maps,
        private readonly MapLocationRepository $locations,
        private readonly ShoreExampleRenderer $renderer,
        private readonly SpriteProfileRepository $profiles,
        private readonly SpriteTypeRepository $types,
        /** Output directory: one o{id}.png file per piece */
        private readonly string $directory,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('ids', InputArgument::IS_ARRAY, 'Object ids or ranges (374-383). All the shore pieces by default');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $shorePieces = ShoreExampleRenderer::shorePieces($this->profiles->all());
        } catch (RuntimeException $e) {
            $io->error($e->getMessage() . ': run sprites:analyze first');

            return self::FAILURE;
        }

        $selected = [];

        foreach ((array) $input->getArgument('ids') as $id) {
            [$from, $to] = str_contains((string) $id, '-') ? explode('-', (string) $id, 2) : [$id, $id];
            $selected += array_fill_keys(range((int) $from, (int) $to), true);
        }

        $pieces = $selected === [] ? $shorePieces : array_intersect_key($shorePieces, $selected);
        $areas = [];

        foreach ($pieces as $profile) {
            foreach ($profile->areas as $area) {
                $areas[$area->id] = true;
            }
        }

        // The maps of the areas using the pieces
        $locations = $this->locations->all();
        $maps = array_filter($this->maps->all(), static fn ($map) => isset($areas[$locations[$map->id]->areaId ?? -1]));
        $progress = $io->createProgressBar(count($maps));
        $found = $this->renderer->find($maps, $this->types->waterGrounds(), array_fill_keys(array_keys($pieces), true), static fn () => $progress->advance());
        $progress->finish();
        $io->newLine(2);

        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Cannot create directory ' . $this->directory);
        }

        foreach ($found as $id => [$map, $cellId]) {
            try {
                imagepng($this->renderer->render($map, $cellId, array_fill_keys(array_keys($shorePieces), true)), sprintf('%s/o%d.png', $this->directory, $id));
                $io->text(sprintf('o%d: map %d, cell %d', $id, $map->id, $cellId));
            } catch (RenderException|RuntimeException $e) {
                $io->warning(sprintf('o%d: %s', $id, $e->getMessage()));
            }
        }

        $io->success(sprintf('%d of %d shore pieces rendered along the water in %s', count($found), count($pieces), $this->directory));

        return self::SUCCESS;
    }
}
