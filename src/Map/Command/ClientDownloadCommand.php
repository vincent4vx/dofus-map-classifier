<?php

declare(strict_types=1);

namespace MapClassifier\Map\Command;

use MapClassifier\Map\Service\ClientDownloader;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function implode;
use function is_dir;
use function sprintf;

#[AsCommand('client:download', 'Download a Dofus Retro client from dofedex (only the maps and sprites by default)')]
final class ClientDownloadCommand extends Command
{
    public function __construct(
        private readonly ClientDownloader $downloader,
        private readonly string $defaultUrl,
        private readonly string $defaultOutput,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'URL of the client zip archive', $this->defaultUrl)
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Directory of the client. Use it as DOFUS_PATH (or DOFUS_CLIENT_DIR with docker compose)', $this->defaultOutput)
            ->addOption('full', null, InputOption::VALUE_NONE, 'Extract all the client files, not only ' . implode(' and ', ClientDownloader::DIRECTORIES))
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Download again if the client is already present')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = (string) $input->getOption('url');
        $dir = (string) $input->getOption('output');

        if (!$input->getOption('force') && is_dir($dir . '/data/maps')) {
            $io->success('Client already present in ' . $dir . ' (use --force to download it again)');

            return self::SUCCESS;
        }

        $io->text('Downloading ' . $url);
        $count = $this->downloader->download($url, $dir, $input->getOption('full') ? null : ClientDownloader::DIRECTORIES);
        $io->success(sprintf('%d client files extracted to %s', $count, $dir));

        return self::SUCCESS;
    }
}
