<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Service;

use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Classification\Service\SubAreaBiomeResolver;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Sprite\Model\SpriteAnalysisReport;
use MapClassifier\Sprite\Repository\InteractiveObjectRepository;
use MapClassifier\Sprite\Repository\SpriteRepository;

/**
 * Analyze the sprites used by the maps: load the maps data (locations, classifications, interactive objects),
 * extract the sprites of each map, and aggregate the maps data on them with {@see SpriteAnalyzer}
 */
final readonly class SpriteAnalysis
{
    public function __construct(
        private MapSpriteExtractor $extractor,
        private MapLocationRepository $locations,
        private MapResultRepository $results,
        private InteractiveObjectRepository $interactiveObjects,
        private SpriteRepository $sprites,
        private SubAreaBiomeResolver $subAreaBiomes = new SubAreaBiomeResolver(),
    ) {}

    /**
     * @param iterable<MapFile> $maps Maps to analyze
     * @param float $minRatio Attribute values with a count lower than this ratio of the main value are ignored
     * @param callable(string): void $onWarning Called when optional data is not available (locations, interactive objects)
     * @param callable(MapFile): void|null $onMap Called after each map
     */
    public function analyze(iterable $maps, float $minRatio, callable $onWarning, ?callable $onMap = null): SpriteAnalysisReport
    {
        $locations = $this->locations->tryAll(static fn (string $error) => $onWarning('Areas not available: ' . $error));
        $results = $this->results->all();

        $analyzer = new SpriteAnalyzer(
            $locations,
            $results,
            $this->subAreaBiomes->resolve($locations, $results),
            $minRatio,
            $this->interactiveObjects->tryByGfx(static fn (string $error) => $onWarning('Interactive objects not available: ' . $error)),
            $this->sprites,
        );

        $analyzed = 0;
        $skipped = [];

        foreach ($maps as $map) {
            try {
                $analyzer->add($map->id, $this->extractor->extract($map));
                ++$analyzed;
            } catch (RenderException $e) {
                $skipped[$e->error->value] = ($skipped[$e->error->value] ?? 0) + 1;
            }

            if ($onMap) {
                $onMap($map);
            }
        }

        return new SpriteAnalysisReport($analyzer->profiles(), $analyzed, $skipped, $analyzer->areaProfiles());
    }
}
