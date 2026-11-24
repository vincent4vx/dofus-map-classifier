<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Command;

use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Sprite\Classification\Model\Area\AreaExport;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Classification\Service\AreaOverviewBuilder;
use MapClassifier\Sprite\Classification\Service\SpriteTypeResolver;
use MapClassifier\Sprite\Repository\AreaProfileRepository;
use MapClassifier\Sprite\Repository\SpriteProfileRepository;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function glob;
use function sprintf;
use function unlink;

#[AsCommand('areas:export', 'Export each area in its own file: its terrain and paths by location type, and the usage of its sprites')]
final class AreasExportCommand extends Command
{
    public function __construct(
        private readonly AreaProfileRepository $areas,
        private readonly SpriteProfileRepository $profiles,
        private readonly SpriteTypeRepository $types,
        /** Output directory: one {id}.json file per area */
        private readonly string $directory,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $areas = $this->areas->all();
            $builder = new AreaOverviewBuilder(new SpriteTypeResolver($this->types), $this->profiles->all());
        } catch (RuntimeException $e) {
            $io->error($e->getMessage() . ': run sprites:analyze first');

            return self::FAILURE;
        }

        // Areas no longer analyzed must not survive from an earlier export
        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            unlink($file);
        }

        foreach ($areas as $area) {
            JsonFile::write(sprintf('%s/%d.json', $this->directory, $area->id), new AreaExport($builder->build($area), $area));
        }

        $io->success(sprintf('%d areas exported to %s', count($areas), $this->directory));

        return self::SUCCESS;
    }
}
