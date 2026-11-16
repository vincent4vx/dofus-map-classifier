<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Command;

use InvalidArgumentException;
use MapClassifier\Infrastructure\Console\EnumOption;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\SpriteFilter;
use MapClassifier\Sprite\Model\SpriteSelection;
use MapClassifier\Sprite\Model\SpriteSet;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use function implode;

/**
 * Command line arguments and options used to build a {@see SpriteFilter}
 */
final class SpriteFilterInput
{
    /**
     * Configure the "ids" argument and the filter options on the command
     */
    public static function configure(Command $command): void
    {
        $command
            ->addArgument('ids', InputArgument::IS_ARRAY, 'Sprite ids or ranges, prefixed by "g" for grounds or "o" for objects (e.g. o345 g10-20). Without prefix, both sets are selected')
            ->addOption('set', null, InputOption::VALUE_REQUIRED, 'Only this sprite set (' . implode(', ', EnumOption::values(SpriteSet::class)) . ')')
            ->addOption('layer', null, InputOption::VALUE_REQUIRED, 'Only sprites mainly used on this layer (' . implode(', ', EnumOption::values(Layer::class)) . ')')
            ->addOption('interactive', null, InputOption::VALUE_NONE, 'Only sprites of interactive objects (resources, workshops…)')
            ->addOption('min-maps', null, InputOption::VALUE_REQUIRED, 'Only sprites used on at least this number of maps', '0')
        ;
    }

    /**
     * @throws InvalidArgumentException When an argument or an option is invalid
     */
    public static function filter(InputInterface $input): SpriteFilter
    {
        return new SpriteFilter(
            selection: SpriteSelection::parse($input->getArgument('ids')),
            set: EnumOption::get($input, 'set', SpriteSet::class),
            layer: EnumOption::get($input, 'layer', Layer::class),
            minMaps: (int) $input->getOption('min-maps'),
            interactiveOnly: (bool) $input->getOption('interactive'),
        );
    }
}
