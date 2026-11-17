<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Service;

use Arakne\MapParser\Parser\Cell;
use MapClassifier\Infrastructure\OpenAI\ResponsesRequest;
use MapClassifier\Sprite\Classification\Model\SpriteCategory;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\SpriteMetadata;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Model\UsageCount;

use function array_column;
use function array_keys;
use function array_map;
use function array_slice;
use function arsort;
use function implode;
use function in_array;
use function round;
use function sprintf;

/**
 * Build the Responses API request used to find the type of a sprite
 */
final readonly class SpriteTypePrompt
{
    /** Added to the context when the example map is sent */
    public const string EXAMPLE_CONTEXT = 'The second image is an example map using the sprite: the sprite is framed in magenta.';

    public function __construct(
        private string $model,
        private ?string $reasoningEffort = null,
    ) {}

    /**
     * @param SpriteMetadata $metadata Metadata of the exported sprite (size, frames)
     * @param string $imageUrl Image of the sprite
     * @param string|null $exampleUrl Example map using the sprite, with a magenta frame around it (see {@see \MapClassifier\Sprite\Service\SpriteExampleRenderer})
     *
     * @return array<string, mixed>
     */
    public function requestBody(SpriteProfile $profile, SpriteMetadata $metadata, string $imageUrl, ?string $exampleUrl = null): array
    {
        return ResponsesRequest::structured(
            $this->model,
            self::instructions(),
            self::context($profile, $metadata) . ($exampleUrl !== null ? "\n" . self::EXAMPLE_CONTEXT : ''),
            $exampleUrl !== null ? [$imageUrl, $exampleUrl] : [$imageUrl],
            'sprite_type',
            self::schema(),
            $this->reasoningEffort,
        );
    }

    /**
     * Describe what is known about the sprite usage in maps
     *
     *      */
    public static function context(SpriteProfile $profile, SpriteMetadata $metadata): string
    {
        $counts = static fn (array $values) => implode(', ', array_map(
            // Number of maps: the total of uses is not given to the model
            static fn (string $name, int|UsageCount $count) => $name . ' (' . ($count instanceof UsageCount ? $count->maps : $count) . ')',
            array_keys($values),
            $values,
        ));
        $named = static function (array $values) use ($counts) {
            $byName = [];

            foreach (array_slice($values, 0, 6) as $value) {
                $byName[$value->label()] = $value->maps;
            }

            return $counts($byName);
        };

        $lines = [
            'Identify the sprite ' . $profile->set->spriteName($profile->id) . '.',
            sprintf('Visible size: %s x %s game pixels (one map cell is 53 x 27 pixels).', $metadata->size->width, $metadata->size->height),
            sprintf('Mainly used as: %s (%s).', $profile->layer->value, self::layerDescription($profile->layer)),
            sprintf('Used %d times, on %d maps.', $profile->occurrences, $profile->maps),
        ];

        if ($profile->slope) {
            $lines[] = 'Also used on sloped ground cells (relief).';
        }

        if ($profile->set === SpriteSet::Objects && in_array($profile->id, Cell::TELEPORT_OBJECT_NUMBERS, true)) {
            $lines[] = 'Known sprite: this is the teleport cell marker (cell used to change map), category "marker".';
        }

        if ($profile->interactive !== null) {
            $lines[] = sprintf(
                'Known interactive object (from the game data): "%s", %s. Use this name, and choose the category of what the sprite shows.',
                $profile->interactive->name,
                $profile->interactive->typeDescription(),
            );
        }

        if ($profile->biomes) {
            $lines[] = 'Biomes of these maps (number of maps): ' . $counts($profile->biomes) . '.';
        }

        if ($profile->areas) {
            $lines[] = 'Areas: ' . $named($profile->areas) . '.';
        }

        if ($profile->subAreas) {
            $lines[] = 'Sub areas: ' . $named($profile->subAreas) . '.';
        }

        if ($profile->indoor) {
            $lines[] = 'Indoor / outdoor maps: ' . $counts($profile->indoor) . '.';
        }

        if ($profile->locationTypes) {
            $lines[] = 'Kind of places: ' . $counts($profile->locationTypes) . '.';
        }

        if ($movements = self::movementsDescription($profile->movements)) {
            $lines[] = 'Map cells where it is placed: ' . $movements . '.';
        }

        return implode("\n", $lines);
    }

    /**
     * Describe the movement of the cells using the sprite, grouped as walkable, blocked and interactive object
     *
     * @param array<int, float> $movements Frequency of each movement (0: not walkable, 1: interactive object, 2 to 7: walkable)
     */
    public static function movementsDescription(array $movements): string
    {
        $groups = ['walkable' => 0.0, 'blocked (not walkable)' => 0.0, 'interactive object' => 0.0];

        foreach ($movements as $movement => $frequency) {
            $groups[match ($movement) {
                0 => 'blocked (not walkable)',
                1 => 'interactive object',
                default => 'walkable',
            }] += $frequency;
        }

        arsort($groups);
        $parts = [];

        foreach ($groups as $name => $frequency) {
            // Rounded to the percent: less than 1% is ignored
            if (($percent = (int) round($frequency * 100)) > 0) {
                $parts[] = $percent . '% ' . $name;
            }
        }

        return implode(', ', $parts);
    }

    public static function layerDescription(Layer $layer): string
    {
        return match ($layer) {
            Layer::Background => 'background image of the whole map',
            Layer::Ground => 'ground tile covering one cell, possibly with relief',
            Layer::Layer1 => 'element drawn flat on the ground, below characters: paths, carpets, shadows, small vegetation, ground details',
            Layer::Layer2 => 'element standing on the ground, drawn above the floor: trees, walls, buildings, furniture, objects',
        };
    }

    public static function instructions(): string
    {
        $categories = implode("\n", array_map(
            static fn (SpriteCategory $category) => '- ' . $category->value . ': ' . $category->description(),
            SpriteCategory::cases(),
        ));

        return <<<PROMPT
            You identify sprites (graphic elements) used to build the maps of the MMORPG Dofus Retro (Dofus 1.29), a 2D isometric game.
            You receive the image of one sprite, cropped and enlarged, on a neutral grey background (the grey is not part of the sprite),
            and information about how the sprite is used in the game maps. Use the image first; use the usage information to resolve ambiguities.
            You may also receive a second image: a screenshot of a game map using the sprite, where one use of the sprite is surrounded by a magenta rectangle.
            The magenta frame is not part of the map. Inside the frame, the sprite may be partly hidden by elements drawn above it,
            and other copies of the sprite may be visible elsewhere on the map. Use this map to understand what the sprite is and what it is part of
            (e.g. a section of a wall, a piece of a house, a part of a ground pattern), but describe the sprite itself, not the whole map.

            Fields:
            - category: the closest category.
            {$categories}
            - name: short and precise lowercase English name of the element, 1 to 4 words (e.g. "pine tree", "wooden fence", "stone well", "wheat field", "grass tile").
            - keywords: 3 to 10 lowercase English search keywords describing the sprite itself: what it is, material, colors, shape, style, and synonyms (e.g. "tree", "conifer", "pine", "green", "snowy").
              Do not use biome, area or place names from the usage information (they are already known), nor generic words like "isometric", "sprite", "tile", "game", "dofus".
            - description: one short English sentence describing the sprite.

            Notes:
            - Sprites are often parts of larger elements (a wall section, a piece of roof, a corner of a carpet): name the part (e.g. "stone wall section").
            - Mostly transparent or very small abstract shapes, as well as dark semi-transparent spots, are usually shadows, effects or markers.
            - A flat "sun" shape (a ring with yellow and green rays) drawn on the ground is the game marker of the cells used to change map: category "marker", name "map change marker".
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['category', 'name', 'keywords', 'description'],
            'properties' => [
                'category' => ['type' => 'string', 'enum' => array_column(SpriteCategory::cases(), 'value')],
                'name' => ['type' => 'string'],
                'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'description' => ['type' => 'string'],
            ],
        ];
    }
}
