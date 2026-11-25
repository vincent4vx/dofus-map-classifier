<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Shore;

use MapClassifier\Infrastructure\OpenAI\ResponsesRequest;
use MapClassifier\Sprite\Model\SpriteMetadata;
use MapClassifier\Sprite\Model\SpriteProfile;

use function array_slice;
use function implode;
use function round;
use function sprintf;

/**
 * Build the Responses API request used to find the role of a shore piece, from its image and an example along the water
 * (see {@see \MapClassifier\Sprite\Service\ShoreExampleRenderer})
 */
final readonly class ShoreRolePrompt
{
    public function __construct(
        private string $model,
        private ?string $reasoningEffort = null,
    ) {}

    /**
     * @param string $imageUrl Image of the sprite
     * @param string $exampleUrl The sprite along the water, framed in magenta, with only the ground and the shore pieces
     *
     * @return array<string, mixed>
     */
    public function requestBody(SpriteProfile $profile, SpriteMetadata $metadata, string $imageUrl, string $exampleUrl): array
    {
        return ResponsesRequest::structured(
            $this->model,
            self::instructions(),
            self::context($profile, $metadata),
            [$imageUrl, $exampleUrl],
            'shore_role',
            self::schema(),
            $this->reasoningEffort,
        );
    }

    public static function instructions(): string
    {
        return <<<'TEXT'
            You analyze the shores of the maps of the 2D isometric game Dofus Retro (2005).
            A map is a grid of diamond cells of 53 x 27 pixels. The shore between the water and the land is drawn with small sprites placed on the cells along it:
            - cliffs: the land stands above the water, with an earthen face going down to the water;
            - grass edges: a grassy rim drawn over the water at the foot of the land;
            - pieces turning the shore around a corner, or ending a stretch (a cliff ending into a grass edge).

            You receive two images: the sprite alone, then a crop of a map where it borders the water. On the map, only the ground, the water and the shore pieces are drawn (trees, buildings and objects are removed), and the sprite is framed in magenta.

            Directions are screen directions: n is up, s is down, e is right, w is left, and ne, se, sw, nw the diagonals. The diagonals follow the edges of the diamond cells: a straight shore runs along them, or horizontally or vertically as a zigzag of cells.

            Describe the role of the framed sprite in the shore:
            - kind: what it draws (cliff, grass_edge, or other);
            - shape: straight (a section of a straight shore, repeated along it), outer_corner (the land forms a convex corner: the shore turns around the land), inner_corner (the water forms a convex corner: the shore turns around the water), end (the shore stops, or changes kind, at this sprite), or other;
            - water: the directions where the water lies around the sprite;
            - land: the directions where the land lies around the sprite;
            - continues: the directions in which the shore goes on beyond the sprite (two for a straight section or a corner, one for an end);
            - description: one short sentence.
            TEXT;
    }

    /**
     * Describe what is known about the piece on the native maps
     */
    public static function context(SpriteProfile $profile, SpriteMetadata $metadata): string
    {
        $lines = [
            'Sprite ' . $profile->set->spriteName($profile->id) . '.',
            sprintf('Visible size: %s x %s game pixels.', $metadata->size->width, $metadata->size->height),
        ];

        if ($profile->shore !== null) {
            $lines[] = sprintf('On the native maps, %s%% of its cells near the water are water cells.', round($profile->shore->waterShare * 100));
            $land = [];

            foreach (array_slice($profile->shore->land, 0, 3, true) as $directions => $share) {
                $land[] = sprintf('%s (%s%%)', $directions === '' ? 'none' : $directions, round($share * 100));
            }

            $lines[] = 'Directions of the land cells around its water cells, the most frequent first: ' . implode(', ', $land) . '.';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $directions = ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ShoreRole::DIRECTIONS]];

        return [
            'type' => 'object',
            'properties' => [
                'kind' => ['type' => 'string', 'enum' => ShoreRole::KINDS],
                'shape' => ['type' => 'string', 'enum' => ShoreRole::SHAPES],
                'water' => $directions,
                'land' => $directions,
                'continues' => $directions,
                'description' => ['type' => 'string'],
            ],
            'required' => ['kind', 'shape', 'water', 'land', 'continues', 'description'],
            'additionalProperties' => false,
        ];
    }
}
