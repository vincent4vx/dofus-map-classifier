<?php

declare(strict_types=1);

namespace MapClassifier\ObjectType;

use Arakne\MapParser\Parser\Cell;
use MapClassifier\Objects\Layer;
use MapClassifier\Objects\ObjectProfile;
use MapClassifier\Objects\SpriteSet;

use function array_column;
use function array_keys;
use function array_map;
use function array_slice;
use function implode;
use function in_array;
use function sprintf;

/**
 * Build the Responses API request used to find the type of a sprite
 */
final readonly class ObjectTypePrompt
{
    public function __construct(
        private string $model,
        private ?string $reasoningEffort = null,
    ) {}

    /**
     * @param array{width: float, height: float} $size Visible size of the sprite, in game pixels
     *
     * @return array<string, mixed>
     */
    public function requestBody(ObjectProfile $profile, string $imageUrl, array $size): array
    {
        $body = [
            'model' => $this->model,
            'instructions' => self::instructions(),
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => self::context($profile, $size)],
                        ['type' => 'input_image', 'image_url' => $imageUrl, 'detail' => 'high'],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'sprite_type',
                    'strict' => true,
                    'schema' => self::schema(),
                ],
            ],
        ];

        if ($this->reasoningEffort !== null) {
            $body['reasoning'] = ['effort' => $this->reasoningEffort];
        }

        return $body;
    }

    /**
     * Describe what is known about the sprite usage in maps
     *
     * @param array{width: float, height: float} $size
     */
    public static function context(ObjectProfile $profile, array $size): string
    {
        $counts = static fn (array $values) => implode(', ', array_map(
            static fn (string $name, int $count) => $name . ' (' . $count . ')',
            array_keys($values),
            $values,
        ));
        $named = static function (array $values) use ($counts) {
            $byName = [];

            foreach (array_slice($values, 0, 6) as $value) {
                $byName[$value['name'] ?? '#' . $value['id']] = $value['maps'];
            }

            return $counts($byName);
        };

        $lines = [
            sprintf('Identify the sprite %s%d.', $profile->set === SpriteSet::Grounds ? 'g' : 'o', $profile->id),
            sprintf('Visible size: %s x %s game pixels (one map cell is 53 x 27 pixels).', $size['width'], $size['height']),
            sprintf('Mainly used as: %s (%s).', $profile->layer->value, self::layerDescription($profile->layer)),
            sprintf('Used %d times, on %d maps.', $profile->occurrences, $profile->maps),
        ];

        if ($profile->set === SpriteSet::Objects && in_array($profile->id, Cell::TELEPORT_OBJECT_NUMBERS, true)) {
            $lines[] = 'Known sprite: this is the teleport cell marker (cell used to change map), category "marker".';
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

        return implode("\n", $lines);
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
            static fn (ObjectCategory $category) => '- ' . $category->value . ': ' . $category->description(),
            ObjectCategory::cases(),
        ));

        return <<<PROMPT
            You identify sprites (graphic elements) used to build the maps of the MMORPG Dofus Retro (Dofus 1.29), a 2D isometric game.
            You receive the image of one sprite, cropped and enlarged, on a neutral grey background (the grey is not part of the sprite),
            and information about how the sprite is used in the game maps. Use the image first; use the usage information to resolve ambiguities.

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
                'category' => ['type' => 'string', 'enum' => array_column(ObjectCategory::cases(), 'value')],
                'name' => ['type' => 'string'],
                'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'description' => ['type' => 'string'],
            ],
        ];
    }
}
