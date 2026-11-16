<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\Export;

use RuntimeException;

use function file_get_contents;
use function is_string;
use function preg_match_all;
use function strtr;

/**
 * HTML page template, with "{{name}}" placeholders
 * Values are inserted as is: they must be escaped by the caller
 */
final readonly class HtmlTemplate
{
    public function __construct(
        private string $file,
    ) {}

    /**
     * @param array<string, string> $values Values indexed by placeholder name
     *
     * @throws RuntimeException When the template cannot be read, or a placeholder has no value
     */
    public function render(array $values): string
    {
        $template = file_get_contents($this->file);

        if (!is_string($template)) {
            throw new RuntimeException('Cannot read the template ' . $this->file);
        }

        $replacements = [];

        preg_match_all('/\{\{(\w+)\}\}/', $template, $matches);

        foreach ($matches[1] as $name) {
            $replacements['{{' . $name . '}}'] = $values[$name] ?? throw new RuntimeException('Missing value for {{' . $name . '}} in the template ' . $this->file);
        }

        return strtr($template, $replacements);
    }
}
