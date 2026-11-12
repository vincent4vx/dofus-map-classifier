<?php

declare(strict_types=1);

namespace MapClassifier\Tests;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\After;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

trait TempDirTrait
{
    private ?string $tempDir = null;

    protected function tempDir(): string
    {
        if ($this->tempDir === null) {
            $this->tempDir = sys_get_temp_dir() . '/map-classifier-test-' . uniqid();
            mkdir($this->tempDir, 0755, true);
        }

        return $this->tempDir;
    }

    #[After]
    protected function removeTempDir(): void
    {
        if ($this->tempDir === null) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->tempDir);
        $this->tempDir = null;
    }
}
