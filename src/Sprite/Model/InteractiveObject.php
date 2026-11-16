<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use function array_search;

/**
 * Interactive object (resource, workshop, zaap, door…) of the "interactiveobjects" lang file
 */
final readonly class InteractiveObject
{
    /**
     * Name of the types: types have no name in the langs, so they are deduced from the objects of each type
     */
    private const array TYPES = [
        1 => 'resource',
        2 => 'workshop',
        3 => 'zaap',
        4 => 'fountain',
        5 => 'door',
        6 => 'storage',
        7 => 'pot',
        10 => 'zaapi',
        12 => 'craftsmen list',
        13 => 'paddock',
        14 => 'switch',
        15 => 'class statue',
    ];

    public function __construct(
        public int $id,
        public string $name,
        /** Type id ("t" field of the lang) */
        public int $type,
    ) {}

    /**
     * Create from the array of {@see toArray()}
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $type = array_search($data['type'] ?? null, self::TYPES, true);

        return new self((int) ($data['id'] ?? 0), (string) ($data['name'] ?? ''), $type === false ? 0 : $type);
    }

    public function typeName(): string
    {
        return self::TYPES[$this->type] ?? 'other';
    }

    /**
     * Description of the type, given to the model
     */
    public function typeDescription(): string
    {
        return match ($this->type) {
            1 => 'resource harvested by a profession: tree, ore, cereal, plant, fish…',
            2 => 'workshop used by a profession to craft items',
            3 => 'zaap: teleportation portal',
            4 => 'fountain giving life',
            5 => 'door of a house or building',
            6 => 'storage: safe or trash',
            7 => 'pot',
            10 => 'zaapi: teleportation post inside a city',
            12 => 'board listing the craftsmen',
            13 => 'paddock for mounts',
            14 => 'switch or mechanism activated by players',
            15 => 'class statue',
            default => 'interactive object',
        };
    }

    /**
     * @return array{id: int, name: string, type: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'type' => $this->typeName()];
    }
}
