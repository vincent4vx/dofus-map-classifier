<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Command;

use InvalidArgumentException;
use MapClassifier\Infrastructure\Console\EnumOption;
use MapClassifier\Infrastructure\Console\ProgressOutput;
use MapClassifier\Infrastructure\Export\ExportFormat;
use MapClassifier\Sprite\Classification\Model\Export\SpriteExport;
use MapClassifier\Sprite\Classification\Model\SpriteTypeResult;
use MapClassifier\Sprite\Classification\Service\SpriteExporter;
use MapClassifier\Sprite\Classification\Service\SpritesToClassify;
use MapClassifier\Sprite\Classification\Service\SpriteTypeClassifier;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\SpriteSelection;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Repository\SpriteProfileRepository;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function implode;
use function max;
use function sprintf;

#[AsCommand('sprites:classify', 'Find the type of sprites (category, name, keywords) using the OpenAI Responses API')]
final class SpritesClassifyCommand extends Command
{
    public function __construct(
        private readonly SpriteProfileRepository $profiles,
        private readonly SpritesToClassify $spritesToClassify,
        private readonly SpriteTypeClassifier $classifier,
        private readonly SpriteExporter $exporter,
        /** @var list<ExportFormat<SpriteExport>> Export formats, written to their default output after the classification */
        private readonly array $exportFormats,
        private readonly string $model,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('ids', InputArgument::IS_ARRAY, 'Sprite ids or ranges, prefixed by "g" for grounds or "o" for objects (e.g. o345 g10-20). Without prefix, both sets are selected')
            ->addOption('set', null, InputOption::VALUE_REQUIRED, 'Only this sprite set (grounds or objects)')
            ->addOption('layer', null, InputOption::VALUE_REQUIRED, 'Only sprites mainly used on this layer (background, ground, layer1, layer2)')
            ->addOption('interactive', null, InputOption::VALUE_NONE, 'Only sprites of interactive objects (resources, workshops…)')
            ->addOption('min-maps', null, InputOption::VALUE_REQUIRED, 'Only sprites used on at least this number of maps', '1')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of sprites to classify (most used first)')
            ->addOption('concurrency', 'c', InputOption::VALUE_REQUIRED, 'Number of parallel requests', '10')
            ->addOption('retry-errors', null, InputOption::VALUE_NONE, 'Classify again sprites with an error')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Classify again already classified sprites')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $profiles = $this->profiles->all();
        } catch (RuntimeException $e) {
            $io->error($e->getMessage() . ': run sprites:analyze and sprites:images first');

            return self::FAILURE;
        }

        try {
            $set = EnumOption::get($input, 'set', SpriteSet::class);
            $layer = EnumOption::get($input, 'layer', Layer::class);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return self::INVALID;
        }

        $option = static fn (string $name) => $input->getOption($name);
        $selected = $this->spritesToClassify->select(
            $profiles,
            SpriteSelection::parse($input->getArgument('ids')),
            set: $set,
            layer: $layer,
            minMaps: (int) $option('min-maps'),
            interactiveOnly: (bool) $option('interactive'),
            force: (bool) $option('force'),
            retryErrors: (bool) $option('retry-errors'),
            limit: $option('limit') !== null ? (int) $option('limit') : null,
        );
        $todo = $selected['sprites'];

        if ($selected['missingImages']) {
            $io->warning(sprintf('%d sprites ignored: image not exported (run sprites:images)', $selected['missingImages']));
        }

        if (!$todo) {
            $io->success('Nothing to classify');

            return self::SUCCESS;
        }

        $io->text(sprintf('Classifying %d sprites (model: %s)', count($todo), $this->model));

        $ok = 0;
        $errors = 0;
        $progress = new ProgressOutput($io, count($todo));

        $this->classifier->classify($todo, max(1, (int) $option('concurrency')), static function (SpriteTypeResult $result) use ($output, $progress, &$ok, &$errors) {
            $name = $result->set->spriteName($result->id);

            $classification = $result->ok ? $result->classification : null;

            if ($classification !== null) {
                ++$ok;
                $message = sprintf('%s: %s (%s)', $name, $classification->name, $classification->category->value);
            } else {
                ++$errors;
                $message = sprintf('<comment>%s: %s%s</comment>', $name, $result->error?->value, $result->errorMessage !== null ? ' (' . $result->errorMessage . ')' : '');
            }

            if ($output->isVerbose() || !$result->ok) {
                $progress->writeln($message);
            }

            $progress->advance($name);
        });

        $progress->finish();

        // Export again, to join the new types
        $export = $this->exporter->export($profiles);

        foreach ($this->exportFormats as $format) {
            $io->text('Types exported to ' . implode(', ', $format->write($export, $format->defaultOutput())));
        }

        $errors
            ? $io->warning(sprintf('%d sprites classified, %d errors', $ok, $errors))
            : $io->success(sprintf('%d sprites classified', $ok));

        return self::SUCCESS;
    }
}
