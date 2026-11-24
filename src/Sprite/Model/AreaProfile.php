<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

use function is_array;

/**
 * Statistics of the sprites on the maps of one area: how the area is built (areas/{id}.json)
 *
 * A sprite can be used very differently from one area to another: the cobblestone patch o991 draws the roads of Pandala,
 * but is a scattered decoration in Sidimote. The global {@see SpriteProfile} mixes them, this profile does not.
 *
 * JSON: {"id": int, "name": string | null, "maps": {@see AreaMaps}, "sprites": {"grounds": {id: {@see AreaSprite}}, "objects": {id: {@see AreaSprite}}}}
 */
final readonly class AreaProfile implements JsonSerializable
{
    public function __construct(
        public int $id,
        public ?string $name,
        public AreaMaps $maps,
        /** @var array<string, array<int, AreaSprite>> Indexed by sprite set value, then sprite id */
        public array $sprites,
    ) {}

    public function sprite(SpriteSet $set, int $id): ?AreaSprite
    {
        return $this->sprites[$set->value][$id] ?? null;
    }

    /**
     * @return array<int, AreaSprite> Indexed by sprite id
     */
    public function bySet(SpriteSet $set): array
    {
        return $this->sprites[$set->value] ?? [];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $sprites = [];

        foreach (SpriteSet::cases() as $set) {
            foreach (is_array($data['sprites'][$set->value] ?? null) ? $data['sprites'][$set->value] : [] as $id => $sprite) {
                if (is_array($sprite)) {
                    $sprites[$set->value][(int) $id] = AreaSprite::fromArray($set, (int) $id, $sprite);
                }
            }
        }

        return new self(
            (int) ($data['id'] ?? 0),
            isset($data['name']) ? (string) $data['name'] : null,
            AreaMaps::fromArray(is_array($data['maps'] ?? null) ? $data['maps'] : []),
            $sprites,
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $sprites = [];

        foreach (SpriteSet::cases() as $set) {
            $sprites[$set->value] = (object) ($this->sprites[$set->value] ?? []);
        }

        return ['id' => $this->id, 'name' => $this->name, 'maps' => $this->maps, 'sprites' => $sprites];
    }
}
