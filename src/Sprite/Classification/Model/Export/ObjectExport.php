<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model\Export;

use JsonSerializable;
use Override;

use function array_filter;
use function array_map;
use function array_sum;
use function count;

/**
 * Export of all sprites with their type (objects.json)
 *
 * JSON: {"grounds": {id: {@see ExportedSprite}}, "objects": {id: {@see ExportedSprite}}}
 */
final readonly class ObjectExport implements JsonSerializable
{
    public function __construct(
        /** @var array<string, array<int, ExportedSprite>> Sprites indexed by set value, then sprite id */
        public array $sprites,
    ) {}

    /**
     * Total number of sprites
     */
    public function count(): int
    {
        return array_sum(array_map(count(...), $this->sprites));
    }

    /**
     * Number of sprites with a type
     */
    public function classified(): int
    {
        return array_sum(array_map(
            static fn (array $sprites) => count(array_filter($sprites, static fn (ExportedSprite $sprite) => $sprite->type !== null)),
            $this->sprites,
        ));
    }

    /**
     * @return array<string, object>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        // Keep sprites indexed by id (JSON object) even if ids are sequential
        return array_map(static fn (array $sprites) => (object) $sprites, $this->sprites);
    }
}
