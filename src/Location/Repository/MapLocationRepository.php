<?php

declare(strict_types=1);

namespace MapClassifier\Location\Repository;

use Arakne\Swf\SwfFile;
use ArrayAccess;
use MapClassifier\Infrastructure\Lang\LangFile;
use MapClassifier\Location\Model\MapLocation;
use RuntimeException;

use function is_array;
use function is_int;
use function is_iterable;
use function is_numeric;
use function is_string;
use function str_starts_with;
use function substr;

/**
 * Load maps locations (sub area, area, super area) from the "maps_{lang}_{version}.swf" lang file
 * The file is executed using ArakneSwf, and the "MA" variable is read:
 * - MA.m[mapId] = {sa: sub area id, ...}
 * - MA.sa[subAreaId] = {n: name, a: area id, ...}
 * - MA.a[areaId] = {n: name, sua: super area id}
 * - MA.sua[superAreaId] = name
 */
final class MapLocationRepository
{
    /** @var array<int, MapLocation>|null */
    private ?array $locations = null;
    private ?string $file = null;

    public function __construct(
        private readonly string $langDir,
        private readonly string $lang = 'en',
    ) {}

    /**
     * Path of the lang file used (the one with the highest version)
     *
     * @throws RuntimeException If no lang file is found
     */
    public function file(): string
    {
        return $this->file ??= LangFile::latest($this->langDir, 'maps', $this->lang);
    }

    /**
     * Same as {@see all()}, but return an empty array instead of failing if the lang file is not available
     *
     * @param callable(string): void $onError Called with the error message
     *
     * @return array<int, MapLocation>
     */
    public function tryAll(callable $onError): array
    {
        try {
            return $this->all();
        } catch (RuntimeException $e) {
            $onError($e->getMessage());

            return [];
        }
    }

    public function find(int $mapId): ?MapLocation
    {
        return $this->all()[$mapId] ?? null;
    }

    /**
     * @return array<int, MapLocation> Locations indexed by map id
     */
    public function all(): array
    {
        return $this->locations ??= $this->load();
    }

    /**
     * @return array<int, MapLocation>
     */
    private function load(): array
    {
        $variables = (new SwfFile($this->file()))->variables();
        $ma = $variables['MA'] ?? null;

        if (!$ma instanceof ArrayAccess) {
            throw new RuntimeException('Invalid maps lang file ' . $this->file() . ': missing MA variable');
        }

        $superAreas = [];

        foreach (self::iterate($ma['sua'] ?? null) as $id => $name) {
            $superAreas[(int) $id] = is_string($name) ? self::cleanName($name) : null;
        }

        $areas = [];

        foreach (self::iterate($ma['a'] ?? null) as $id => $area) {
            $areas[(int) $id] = [self::cleanName(self::string($area, 'n')), self::int($area, 'sua')];
        }

        $subAreas = [];

        foreach (self::iterate($ma['sa'] ?? null) as $id => $subArea) {
            $subAreas[(int) $id] = [self::cleanName(self::string($subArea, 'n')), self::int($subArea, 'a')];
        }

        $locations = [];

        foreach (self::iterate($ma['m'] ?? null) as $id => $map) {
            $subAreaId = self::int($map, 'sa');

            if ($subAreaId === null) {
                continue;
            }

            [$subAreaName, $areaId] = $subAreas[$subAreaId] ?? [null, null];
            [$areaName, $superAreaId] = $areaId !== null ? ($areas[$areaId] ?? [null, null]) : [null, null];

            $locations[(int) $id] = new MapLocation(
                mapId: (int) $id,
                subAreaId: $subAreaId,
                subAreaName: $subAreaName,
                areaId: $areaId,
                areaName: $areaName,
                superAreaId: $superAreaId,
                superAreaName: $superAreaId !== null ? ($superAreas[$superAreaId] ?? null) : null,
            );
        }

        return $locations;
    }

    /**
     * Remove the "//" prefix used by the client on some names
     */
    private static function cleanName(?string $name): ?string
    {
        return $name !== null && str_starts_with($name, '//') ? substr($name, 2) : $name;
    }

    /**
     * @return iterable<array-key, mixed>
     */
    private static function iterate(mixed $value): iterable
    {
        return is_iterable($value) ? $value : [];
    }

    private static function string(mixed $object, string $key): ?string
    {
        $value = $object instanceof ArrayAccess || is_array($object) ? ($object[$key] ?? null) : null;

        return is_string($value) ? $value : null;
    }

    private static function int(mixed $object, string $key): ?int
    {
        $value = $object instanceof ArrayAccess || is_array($object) ? ($object[$key] ?? null) : null;

        return is_int($value) || is_numeric($value) ? (int) $value : null;
    }
}
