<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Export;

use BackedEnum;
use MapClassifier\Infrastructure\Export\ExportFormat;
use MapClassifier\Infrastructure\Export\HtmlTemplate;
use MapClassifier\Infrastructure\Filesystem\Path;
use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\Export\ExportedMap;
use MapClassifier\Map\Classification\Model\LocationType;
use MapClassifier\Map\Classification\Model\MapExport;
use Override;

use function array_keys;
use function array_map;
use function count;
use function dirname;
use function file_put_contents;
use function htmlspecialchars;
use function implode;
use function ksort;
use function mb_strtolower;
use function sprintf;

/**
 * HTML page displaying map renders and their classification, with filters, for visual validation
 *
 * @implements ExportFormat<MapExport>
 */
final readonly class HtmlMapExportFormat implements ExportFormat
{
    public function __construct(
        /** Directory of the maps renders */
        private string $rendersDir,
        private string $defaultOutput,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'html';
    }

    #[Override]
    public function defaultOutput(): string
    {
        return $this->defaultOutput;
    }

    #[Override]
    public function write(object $export, string $output): array
    {
        file_put_contents($output, $this->render($export, Path::relativeUrl(dirname($output), $this->rendersDir)));

        return [$output];
    }

    /**
     * @param string $imagesPath Path or URL of the renders directory, from the page
     */
    public function render(MapExport $export, string $imagesPath): string
    {
        $cards = [];
        $areas = [];

        foreach ($export->maps as $map) {
            $cards[] = $this->card($map, $imagesPath);

            if ($map->location?->area?->name !== null) {
                $areas[$map->location->area->name] = true;
            }
        }

        ksort($areas);

        return $this->page(implode("\n", $cards), count($export->maps), array_keys($areas));
    }

    private function card(ExportedMap $map, string $imagesPath): string
    {
        $result = $map->result;
        $location = $map->location;
        $e = static fn (?string $value) => htmlspecialchars((string) $value);
        $c = $result->classification;

        if ($c === null) {
            $body = sprintf('<p class="error">%s: %s</p>', $e($result->error?->value), $e($result->errorMessage));
        } else {
            $tags = implode('', array_map(static fn (string $tag) => '<button type="button" class="tag">' . $e($tag) . '</button>', $c->tags));
            $body = sprintf(
                '<p><b>%s</b>%s · %s · %s</p><p>%s</p><p>%s</p>',
                $e($c->biome->value ?? 'no biome'),
                $c->biomeOther ? ' (' . $e($c->biomeOther) . ')' : '',
                $e($c->locationType->value),
                $c->indoor ? 'indoor' : 'outdoor',
                $tags,
                $e($c->description),
            );
        }

        $place = $location === null ? '' : sprintf(
            '<p class="location">%s › %s <small>(sub area: %s)</small></p>',
            $e($location->area->name ?? '?'),
            $e($location->subArea->name ?? '?'),
            $e($location->subArea->biome->value ?? 'unknown'),
        );

        // Lowercase text used by the full text search
        $search = mb_strtolower(implode(' ', [
            $result->id,
            $location?->area?->name,
            $location?->subArea->name,
            $c?->biomeOther,
            implode(' ', $c->tags ?? []),
            $c?->description,
            $result->errorMessage,
        ]));

        return sprintf(
            '<figure data-status="%s" data-biome="%s" data-location="%s" data-indoor="%s" data-area="%s" data-search="%s"><img loading="lazy" src="%s/%d.png" alt="map %d"><figcaption><h2>#%d</h2>%s%s</figcaption></figure>',
            $result->ok ? 'ok' : 'error',
            $e($c === null ? '' : ($c->biome->value ?? 'none')),
            $e($c?->locationType->value ?? ''),
            $c === null ? '' : ($c->indoor ? 'indoor' : 'outdoor'),
            $e($location->area->name ?? ''),
            $e($search),
            $e($imagesPath),
            $result->id,
            $result->id,
            $result->id,
            $place,
            $body,
        );
    }

    /**
     * @param list<string> $areas
     */
    private function page(string $cards, int $count, array $areas = []): string
    {
        $options = static fn (array $cases) => implode('', array_map(
            static fn (BackedEnum $case) => sprintf('<option value="%1$s">%1$s</option>', $case->value),
            $cases,
        ));
        $biomes = $options(Biome::cases());
        $locations = $options(LocationType::cases());
        $areaOptions = implode('', array_map(
            static fn (string $area) => sprintf('<option value="%1$s">%1$s</option>', htmlspecialchars($area)),
            $areas,
        ));

        return new HtmlTemplate(__DIR__ . '/Template/maps.html')->render([
            'cards' => $cards,
            'count' => (string) $count,
            'biomes' => $biomes,
            'locations' => $locations,
            'areaOptions' => $areaOptions,
        ]);
    }
}
