<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Infrastructure\Export;

use MapClassifier\Infrastructure\Export\HtmlTemplate;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;

final class HtmlTemplateTest extends TestCase
{
    use TempDirTrait;

    public function testRender(): void
    {
        file_put_contents($this->tempDir() . '/page.html', '<h1>{{title}}</h1><p>{{title}} {{count}}</p>{not a placeholder}');

        $this->assertSame(
            '<h1>Maps</h1><p>Maps {{count}}</p>{not a placeholder}',
            new HtmlTemplate($this->tempDir() . '/page.html')->render(['title' => 'Maps', 'count' => '{{count}}', 'unused' => 'x']),
            'Values are not parsed again',
        );
    }

    public function testMissingValue(): void
    {
        file_put_contents($this->tempDir() . '/page.html', '{{title}}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing value for {{title}}');

        new HtmlTemplate($this->tempDir() . '/page.html')->render([]);
    }
}
