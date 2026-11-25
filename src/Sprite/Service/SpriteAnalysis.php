<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Service;

use MapClassifier\Location\Repository\MapLocationRepository;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Classification\Service\SubAreaBiomeResolver;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Sprite\Classification\Model\SpriteUsage;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Model\SpriteAnalysisReport;
use MapClassifier\Sprite\Model\SpriteSet;
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
        /** Stored classifications: the objects classified with the ground usage draw a floor on layer 1 (no model call) */
        private ?SpriteTypeRepository $types = null,
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
            $this->floorObjects(),
        );

        $waterGrounds = $this->waterGrounds();
        $analyzed = 0;
        $skipped = [];

        foreach ($maps as $map) {
            try {
                $analyzer->add($map->id, $this->extractor->extract($map, $waterGrounds));
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

    /**
     * Ground sprites (and backgrounds) classified as water
     *
     * @return array<int, true>
     */
    private function waterGrounds(): array
    {
        return $this->types?->waterGrounds() ?? [];
    }

    /**
     * Object sprites classified with the ground usage: patches drawn flat on layer 1, which may floor a map
     *
     * @return array<int, true>
     */
    private function floorObjects(): array
    {
        $objects = [];

        foreach ($this->types?->all(SpriteSet::Objects) ?? [] as $id => $result) {
            if ($result->classification?->usage === SpriteUsage::Ground) {
                $objects[$id] = true;
            }
        }

        return $objects;
    }
}
