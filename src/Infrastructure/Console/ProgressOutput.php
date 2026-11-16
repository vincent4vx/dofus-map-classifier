<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\Console;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Progress bar displaying the current item, with messages written above it
 */
final readonly class ProgressOutput
{
    private ProgressBar $bar;

    public function __construct(
        private SymfonyStyle $io,
        int $max,
    ) {
        $this->bar = $io->createProgressBar($max);
        $this->bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%');
        $this->bar->setMessage('');
        $this->bar->start();
    }

    /**
     * Write a message above the progress bar
     */
    public function writeln(string $message): void
    {
        $this->bar->clear();
        $this->io->writeln($message);
        $this->bar->display();
    }

    /**
     * Advance the progress bar, and display the last processed item
     */
    public function advance(string $item): void
    {
        $this->bar->setMessage($item);
        $this->bar->advance();
    }

    public function finish(): void
    {
        $this->bar->finish();
        $this->io->newLine(2);
    }
}
