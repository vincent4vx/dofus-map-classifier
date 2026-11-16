<?php

declare(strict_types=1);

namespace MapClassifier\Map\Command;

use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Model\MapSelection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command line arguments and options used to build a {@see MapSelection}
 */
final class MapSelectionInput
{
    /**
     * Configure the "ids" argument and location options on the command
     */
    public static function configure(Command $command, string $idsDescription): void
    {
        $command
            ->addArgument('ids', InputArgument::IS_ARRAY, $idsDescription)
            ->addOption('super-area', null, InputOption::VALUE_REQUIRED, 'Only maps of this super area id (e.g. 3 for Incarnam)')
            ->addOption('area', null, InputOption::VALUE_REQUIRED, 'Only maps of this area id')
            ->addOption('sub-area', null, InputOption::VALUE_REQUIRED, 'Only maps of this sub area id')
        ;
    }

    /**
     * Create the selection from the command input
     * Locations are only loaded when a location option is used
     */
    public static function selection(InputInterface $input, MapLocationRepository $locations): MapSelection
    {
        $selection = MapSelection::parse($input->getArgument('ids'));
        $option = static fn (string $name) => $input->getOption($name) !== null ? (int) $input->getOption($name) : null;
        [$superArea, $area, $subArea] = [$option('super-area'), $option('area'), $option('sub-area')];

        if ($superArea === null && $area === null && $subArea === null) {
            return $selection;
        }

        return $selection->withLocation($locations->all(), $superArea, $area, $subArea);
    }
}
