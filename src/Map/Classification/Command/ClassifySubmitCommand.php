<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Command;

use Closure;
use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Classification\Batch\Service\BatchService;
use MapClassifier\Map\Classification\Service\ImageEncoder;
use MapClassifier\Map\Classification\Service\MapsToClassify;
use MapClassifier\Map\Command\MapSelectionInput;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function round;
use function sprintf;

#[AsCommand('classify:submit', 'Submit rendered maps to the OpenAI Batch API for classification')]
final class ClassifySubmitCommand extends Command
{
    public function __construct(
        private readonly MapsToClassify $mapsToClassify,
        private readonly MapLocationRepository $locations,
        /** @var Closure(string, float, int): BatchService Create the service from the image format, image scale and max file size */
        private readonly Closure $batchService,
        private readonly string $model,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        MapSelectionInput::configure($this, 'Map ids or ranges (e.g. 10000 100-200). All rendered maps by default');
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of maps to submit')
            ->addOption('retry-errors', null, InputOption::VALUE_NONE, 'Submit again maps with a classification error')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Submit again already classified maps')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only build the batch input files, without submitting them')
            ->addOption('image-format', null, InputOption::VALUE_REQUIRED, 'Image format sent to the model (jpeg or png)', ImageEncoder::JPEG)
            ->addOption('image-scale', null, InputOption::VALUE_REQUIRED, 'Scale factor applied to the rendered image before sending it', (string) ImageEncoder::DEFAULT_SCALE)
            ->addOption('max-file-size', null, InputOption::VALUE_REQUIRED, 'Maximum size of a batch input file, in MB', '150')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $todo = $this->mapsToClassify->select(
            MapSelectionInput::selection($input, $this->locations),
            force: (bool) $input->getOption('force'),
            retryErrors: (bool) $input->getOption('retry-errors'),
            limit: $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null,
        );

        if (!$todo) {
            $io->success('Nothing to submit');

            return self::SUCCESS;
        }

        $service = ($this->batchService)(
            (string) $input->getOption('image-format'),
            (float) $input->getOption('image-scale'),
            (int) $input->getOption('max-file-size') * 1024 * 1024,
        );

        $io->text(sprintf('Building batch files for %d maps (model: %s)...', count($todo), $this->model));
        $files = $service->build($todo);

        foreach ($files as $file) {
            $io->text(sprintf(' - %s: %d maps, %s MB', $file->path, count($file->maps), round($file->size / 1024 / 1024, 1)));
        }

        if ($input->getOption('dry-run')) {
            $io->success('Dry run: batch files built but not submitted');

            return self::SUCCESS;
        }

        if (!$io->confirm(sprintf('Submit %d batch(es) to OpenAI?', count($files)))) {
            return self::SUCCESS;
        }

        foreach ($files as $file) {
            $record = $service->submit($file);
            $io->text(sprintf(' - %s submitted as %s (%s)', $file->path, $record->id, $record->info->status));
        }

        $io->success('Batches submitted. Use classify:status to follow them, then classify:import');

        return self::SUCCESS;
    }
}
