<?php

declare(strict_types=1);

namespace MapClassifier\Command;

use MapClassifier\Config;
use MapClassifier\MapKey\MapKeyDownloader;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('keys:download', 'Download map keys from the MapKeys-DR repository')]
final class KeysDownloadCommand extends Command
{
    public function __construct(
        private readonly MapKeyDownloader $downloader,
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'URL of the repository tar.gz archive', $this->config->keysArchiveUrl);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = (string) $input->getOption('url');

        $io->text('Downloading ' . $url);
        $count = $this->downloader->download($url, $this->config->keysDir);
        $io->success($count . ' keys saved to ' . $this->config->keysDir);

        return self::SUCCESS;
    }
}
