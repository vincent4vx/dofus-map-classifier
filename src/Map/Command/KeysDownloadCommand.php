<?php

declare(strict_types=1);

namespace MapClassifier\Map\Command;

use MapClassifier\Map\Service\MapKeyDownloader;
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
        private readonly string $keysDir,
        private readonly string $defaultUrl,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'URL of the repository tar.gz archive', $this->defaultUrl);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = (string) $input->getOption('url');

        $io->text('Downloading ' . $url);
        $count = $this->downloader->download($url, $this->keysDir);
        $io->success($count . ' keys saved to ' . $this->keysDir);

        return self::SUCCESS;
    }
}
