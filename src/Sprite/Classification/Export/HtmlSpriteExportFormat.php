<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Export;

use MapClassifier\Infrastructure\Export\ExportFormat;
use MapClassifier\Infrastructure\Export\HtmlTemplate;
use MapClassifier\Infrastructure\Filesystem\Path;
use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\LocationType;
use MapClassifier\Sprite\Classification\Model\Export\SpriteExport;
use MapClassifier\Sprite\Classification\Model\SpriteCategory;
use MapClassifier\Sprite\Classification\Model\SpriteOrientation;
use MapClassifier\Sprite\Classification\Model\SpriteUsage;
use MapClassifier\Sprite\Model\Layer;
use Override;

use function array_column;
use function array_keys;
use function array_map;
use function dirname;
use function file_put_contents;
use function htmlspecialchars;
use function implode;
use function json_encode;
use function sort;
use function sprintf;
use function str_replace;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * HTML page displaying sprites with their aggregated data and type, with filters
 * Sprites are rendered by JavaScript from a compact JSON list, with pagination
 *
 * @implements ExportFormat<SpriteExport>
 */
final readonly class HtmlSpriteExportFormat implements ExportFormat
{
    public function __construct(
        /** Directory of the sprites images */
        private string $spritesDir,
        private string $defaultOutput,
        /** Directory of the example maps images */
        private ?string $examplesDir = null,
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
        file_put_contents($output, $this->render(
            $export,
            Path::relativeUrl(dirname($output), $this->spritesDir),
            $this->examplesDir !== null ? Path::relativeUrl(dirname($output), $this->examplesDir) : '',
        ));

        return [$output];
    }

    /**
     * The page contains the export, in the same format as the JSON export: sprites are rendered by JavaScript, with pagination
     *
     * @param string $spritesPath Path or URL of the sprites images directory, from the page
     * @param string $examplesPath Path or URL of the example maps directory, from the page
     */
    public function render(SpriteExport $export, string $spritesPath, string $examplesPath = ''): string
    {
        $areas = [];
        $interactiveTypes = [];

        foreach ($export as $sprite) {
            if ($sprite->profile->interactive !== null) {
                $interactiveTypes[$sprite->profile->interactive->typeName()] = true;
            }

            foreach ($sprite->profile->areas as $area) {
                $areas[$area->label()] = true;
            }
        }

        $options = static fn (array $values) => implode('', array_map(
            static fn (string $value) => sprintf('<option value="%1$s">%1$s</option>', htmlspecialchars($value)),
            $values,
        ));
        $sorted = static function (array $values): array {
            $values = array_keys($values);
            sort($values);

            return $values;
        };

        return new HtmlTemplate(__DIR__ . '/Template/sprites.html')->render([
            // Avoid closing the script tag from the data
            'json' => str_replace('</', '<\/', json_encode($export, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'spritesPath' => htmlspecialchars(json_encode($spritesPath, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'examplesPath' => htmlspecialchars(json_encode($examplesPath, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'layers' => $options(array_column(Layer::cases(), 'value')),
            'biomes' => $options(array_column(Biome::cases(), 'value')),
            'locations' => $options(array_column(LocationType::cases(), 'value')),
            'categories' => $options(array_column(SpriteCategory::cases(), 'value')),
            'usages' => $options(array_column(SpriteUsage::cases(), 'value')),
            'orientations' => $options(array_column(SpriteOrientation::cases(), 'value')),
            'areaOptions' => $options($sorted($areas)),
            'interactiveOptions' => $options($sorted($interactiveTypes)),
        ]);
    }
}
