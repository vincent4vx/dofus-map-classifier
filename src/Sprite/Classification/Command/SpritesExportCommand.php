<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Command;

use InvalidArgumentException;
use MapClassifier\Infrastructure\Export\ExportFormat;
use MapClassifier\Infrastructure\Export\ExportFormatOption;
use MapClassifier\Sprite\Classification\Model\Export\SpriteExport;
use MapClassifier\Sprite\Classification\Service\SpriteExporter;
use MapClassifier\Sprite\Command\SpriteFilterInput;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Repository\SpriteProfileRepository;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function implode;
use function sprintf;

#[AsCommand('sprites:export', 'Export the sprites profiles with their type (selection, --set, --layer…): JSON data and HTML gallery with filters')]
final class SpritesExportCommand extends Command
{
    public function __construct(
        private readonly SpriteProfileRepository $profiles,
        private readonly SpriteExporter $exporter,
        /** @var list<ExportFormat<SpriteExport>> */
        private readonly array $formats,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        SpriteFilterInput::configure($this);
        ExportFormatOption::configure($this, $this->formats);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $formats = ExportFormatOption::selected($input, $this->formats);
            $filter = SpriteFilterInput::filter($input);
            $profiles = $this->profiles->all();
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return self::INVALID;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage() . ': run sprites:analyze first');

            return self::FAILURE;
        }

        $export = $this->exporter->export(SpriteProfiles::of($filter->filter($profiles)));

        foreach ($formats as $file => $format) {
            $io->text(sprintf('%s: %s', $format->name(), implode(', ', $format->write($export, $file))));
        }

        $io->success(sprintf('%d sprites exported (%d with a type)', $export->count(), $export->classified()));

        return self::SUCCESS;
    }
}
