<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Infrastructure\Json;

use MapClassifier\Infrastructure\Json\JsonFile;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_get_contents;
use function file_put_contents;

final class JsonFileTest extends TestCase
{
    use TempDirTrait;

    public function testWriteAndRead(): void
    {
        $file = $this->tempDir() . '/sub/dir/data.json';
        JsonFile::write($file, ['name' => 'Éclair/1', 'list' => [1, 2]]);

        $this->assertSame("{\n    \"name\": \"Éclair/1\",\n    \"list\": [\n        1,\n        2\n    ]\n}\n", file_get_contents($file));
        $this->assertSame(['name' => 'Éclair/1', 'list' => [1, 2]], JsonFile::read($file));
        $this->assertFileDoesNotExist($file . '.tmp');
    }

    public function testReadInvalid(): void
    {
        file_put_contents($this->tempDir() . '/invalid.json', '{');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid ' . $this->tempDir() . '/invalid.json');

        JsonFile::read($this->tempDir() . '/invalid.json');
    }

    public function testReadScalar(): void
    {
        file_put_contents($this->tempDir() . '/scalar.json', '12');

        $this->expectException(RuntimeException::class);

        JsonFile::read($this->tempDir() . '/scalar.json');
    }

    public function testReadMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not found');

        JsonFile::read($this->tempDir() . '/missing.json');
    }
}
