<?php

declare(strict_types=1);

namespace MapClassifier\Map\Service;

use MapClassifier\Infrastructure\Http\FileDownloader;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use ZipArchive;

use function count;
use function is_dir;
use function mkdir;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Download a Dofus Retro client archive (e.g. from dofedex) and extract it
 */
final readonly class ClientDownloader
{
    /**
     * Directories of the client used by the project: maps files and sprites
     */
    public const array DIRECTORIES = ['data/maps', 'clips/gfx'];

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    /**
     * Download the zip archive and extract it into $targetDir
     *
     * @param list<string>|null $directories Only extract files of these directories (relative to the client root), or null to extract all files
     *
     * @return int Number of extracted files
     */
    public function download(string $archiveUrl, string $targetDir, ?array $directories = self::DIRECTORIES): int
    {
        $archive = tempnam(sys_get_temp_dir(), 'dofus-client') ?: throw new RuntimeException('Cannot create a temporary file');

        try {
            new FileDownloader($this->httpClient)->download($archiveUrl, $archive, 'the client');

            return $this->extract($archive, $targetDir, $directories);
        } finally {
            @unlink($archive);
        }
    }

    /**
     * @param list<string>|null $directories Only extract files of these directories, or null to extract all files
     *
     * @return int Number of extracted files
     */
    public function extract(string $archive, string $targetDir, ?array $directories = self::DIRECTORIES): int
    {
        $zip = new ZipArchive();

        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Invalid client archive');
        }

        try {
            $files = [];

            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = (string) $zip->getNameIndex($i);

                if (!str_ends_with($name, '/') && !str_contains($name, '..') && $this->selected($name, $directories)) {
                    $files[] = $name;
                }
            }

            if (!$files) {
                throw new RuntimeException('No client file found in the archive');
            }

            if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new RuntimeException('Cannot create directory ' . $targetDir);
            }

            if (!$zip->extractTo($targetDir, $files)) {
                throw new RuntimeException('Cannot extract the client to ' . $targetDir);
            }

            return count($files);
        } finally {
            $zip->close();
        }
    }

    /**
     * @param list<string>|null $directories
     */
    private function selected(string $name, ?array $directories): bool
    {
        if ($directories === null) {
            return true;
        }

        foreach ($directories as $directory) {
            if (str_starts_with($name, $directory . '/')) {
                return true;
            }
        }

        return false;
    }
}
