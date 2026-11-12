<?php

declare(strict_types=1);

namespace MapClassifier\Classification;

use function array_map;
use function implode;

/**
 * Build the OpenAI Responses API request used to classify a map image
 */
final readonly class ClassificationPrompt
{
    public function __construct(
        private string $model,
        private ?string $reasoningEffort = null,
    ) {}

    /**
     * Request body for POST /v1/responses
     *
     * @param string $imageUrl Image as URL or data URL
     *
     * @return array<string, mixed>
     */
    public function requestBody(int $mapId, string $imageUrl): array
    {
        $body = [
            'model' => $this->model,
            'instructions' => self::instructions(),
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => 'Classify the map ' . $mapId . '.'],
                        ['type' => 'input_image', 'image_url' => $imageUrl, 'detail' => 'high'],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'map_classification',
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

    public static function instructions(): string
    {
        $biomes = implode("\n", array_map(
            static fn (Biome $biome) => '- ' . $biome->value . ': ' . $biome->description(),
            Biome::cases(),
        ));
        $locations = implode("\n", array_map(
            static fn (LocationType $type) => '- ' . $type->value . ': ' . $type->description(),
            LocationType::cases(),
        ));

        return <<<PROMPT
            You classify maps of the MMORPG Dofus Retro (Dofus 1.29), a 2D isometric game.
            You receive the rendered image of one map (a single screen of the game world, without characters or monsters).
            Analyze the whole image and answer with the requested JSON.

            Fields:
            - biome: the natural environment of the map, or null if it cannot be determined from the image.
            {$biomes}
              Rules:
              - Outdoor maps: choose the closest value. For towns and villages, use the natural environment visible around the buildings (vegetation, ground, water).
              - Indoor maps (buildings, ships, …): only set a biome if the environment is clearly recognizable from the walls, floor or decoration (e.g. bamboo walls: jungle, ice walls: snow, raw rock walls: cave). Otherwise (plain wooden or stone interior, furniture only), use null.
              - Never use "other" to describe a building, a town or an interior: use null instead.
            - biome_other: short label (1-3 words) only when biome is "other", otherwise null.
            - indoor: true if the map is inside a building, a cave, a mine or any closed place; false if it is outdoor.
            - location_type: the kind of place.
            {$locations}
            - tags: 3 to 10 short lowercase English tags of notable visual elements (e.g. "river", "bridge", "pine trees", "ruins", "wheat field", "well", "fence", "lava", "boat"). Avoid repeating the biome name alone.
            - description: one or two English sentences describing the map.

            Notes:
            - Black areas are outside of the playable map, ignore them.
            - Small sun or star icons on the ground are game markers, ignore them.
            PROMPT;
    }

    /**
     * JSON schema of the expected answer (strict structured output)
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['biome', 'biome_other', 'indoor', 'location_type', 'tags', 'description'],
            'properties' => [
                'biome' => [
                    'anyOf' => [
                        ['type' => 'string', 'enum' => array_map(static fn (Biome $biome) => $biome->value, Biome::cases())],
                        ['type' => 'null'],
                    ],
                ],
                'biome_other' => ['type' => ['string', 'null']],
                'indoor' => ['type' => 'boolean'],
                'location_type' => [
                    'type' => 'string',
                    'enum' => array_map(static fn (LocationType $type) => $type->value, LocationType::cases()),
                ],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'description' => ['type' => 'string'],
            ],
        ];
    }
}
