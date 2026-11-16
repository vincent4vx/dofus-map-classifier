<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Infrastructure\Console;

use InvalidArgumentException;
use MapClassifier\Infrastructure\Console\EnumOption;
use MapClassifier\Sprite\Model\Layer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

final class EnumOptionTest extends TestCase
{
    public function testGet(): void
    {
        $this->assertSame(Layer::Layer2, EnumOption::get($this->input(['--layer' => 'layer2']), 'layer', Layer::class));
        $this->assertNull(EnumOption::get($this->input([]), 'layer', Layer::class));
    }

    public function testInvalidValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid --layer "layer3", expected one of: background, ground, layer1, layer2');

        EnumOption::get($this->input(['--layer' => 'layer3']), 'layer', Layer::class);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function input(array $parameters): ArrayInput
    {
        return new ArrayInput($parameters, new InputDefinition([new InputOption('layer', null, InputOption::VALUE_REQUIRED)]));
    }
}
