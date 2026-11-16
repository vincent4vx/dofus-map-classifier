<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Infrastructure\Export;

use InvalidArgumentException;
use MapClassifier\Infrastructure\Export\ExportFormat;
use MapClassifier\Infrastructure\Export\ExportFormatOption;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;

final class ExportFormatOptionTest extends TestCase
{
    /** @var list<ExportFormat<object>> */
    private array $formats;
    private Command $command;

    #[Override]
    protected function setUp(): void
    {
        $this->formats = [self::format('json', 'var/data.json'), self::format('html', 'var/data.html')];
        $this->command = new Command('test');
        ExportFormatOption::configure($this->command, $this->formats);
    }

    public function testAllFormatsByDefault(): void
    {
        $this->assertSame(
            ['var/data.json' => $this->formats[0], 'var/data.html' => $this->formats[1]],
            ExportFormatOption::selected($this->input([]), $this->formats),
        );
        $this->assertStringContainsString('json: var/data.json, html: var/data.html', $this->command->getDefinition()->getOption('format')->getDescription());
    }

    public function testSelectedFormats(): void
    {
        $this->assertSame(['var/data.html' => $this->formats[1]], ExportFormatOption::selected($this->input(['--format' => ['html']]), $this->formats));
        $this->assertSame(['out.json' => $this->formats[0]], ExportFormatOption::selected($this->input(['--format' => ['json'], '--output' => 'out.json']), $this->formats));
    }

    public function testUnknownFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown format "xml", available: json, html');

        ExportFormatOption::selected($this->input(['--format' => ['json', 'xml']]), $this->formats);
    }

    public function testOutputRequiresSingleFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The --output option requires a single --format');

        ExportFormatOption::selected($this->input(['--output' => 'out.json']), $this->formats);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function input(array $parameters): ArrayInput
    {
        return new ArrayInput($parameters, $this->command->getDefinition());
    }

    /**
     * @return ExportFormat<object>
     */
    private static function format(string $name, string $defaultOutput): ExportFormat
    {
        return new readonly class ($name, $defaultOutput) implements ExportFormat {
            public function __construct(
                private string $name,
                private string $defaultOutput,
            ) {}

            public function name(): string
            {
                return $this->name;
            }

            public function defaultOutput(): string
            {
                return $this->defaultOutput;
            }

            public function write(object $export, string $output): array
            {
                return [$output];
            }
        };
    }
}
