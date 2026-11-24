# data/areas/{id}.json

How the maps of each area of the Dofus Retro 1.29 client are built: their terrain and paths by location type, and the usage of every sprite on the maps of the area. One file per area, named after the area id. Copies of `var/areas/{id}.json`, produced by `make sprites` (`sprites:analyze`, then `areas:export`).

The same sprite can be used very differently from one area to another: the cobblestone patch `o991` draws the roads of Neutral Pandala, but is a scattered decoration of Sidimote Moor. The global profile of [objects.json](objects.md) mixes these uses; these files keep them apart.

- 45 areas. A map counts in the area of its sub area; maps without a known area are left out.
- The statistics are computed from the maps only: no model call. The type of the sprites (`name`, `category`, `transition`) is the global classification of [objects.json](objects.md#type).
- The distributions are complete: unlike in `objects.json`, no value is ignored as noise.

## Structure

```json
{
  "id": 19,
  "name": "Neutral Pandala",
  "maps": AreaMaps,
  "location_types": {"road": LocationOverview, "village": LocationOverview},
  "sprites": {
    "grounds": {"253": AreaSprite},
    "objects": {"991": AreaSprite}
  }
}
```

- `maps`: number of maps of the area (see [Maps](#maps)).
- `location_types`: summary of the maps of each location type, for their generation (see [Location overview](#location-overview)).
- `sprites`: usage of each sprite on the maps of the area, by set then sprite number (see [Sprites](#sprites)).

## Maps

The denominators of the usages: to know how often a sprite is used, compare its number of maps with the number of maps of the area, or of the location type.

```json
{
  "total": 249,
  "classified": 249,
  "with_path": 0,
  "indoor": {"outdoor": 165, "indoor": 84},
  "biomes": {"jungle": 140, "other": 60},
  "location_types": {"road": {"maps": 42, "with_path": 0}, "village": {"maps": 29, "with_path": 0}}
}
```

| Field | Type | Description |
|---|---|---|
| `total` | int | Analyzed maps of the area (maps without a decryption key are not analyzed) |
| `classified` | int | Classified maps among them |
| `with_path` | int | Maps with a preferred path (see [Path](objects.md#path)) |
| `indoor` | object | Classified maps, by `indoor` / `outdoor` |
| `biomes` | object | Maps by biome: the map biome, or else the biome of its sub area |
| `location_types` | object | Classified maps of each location type (`maps`), and those with a preferred path (`with_path`). Keys: see [maps.md](maps.md#location-types) |

## Location overview

For each location type of the area, the sprites building its terrain and its paths, as a generator needs them. A sprite is listed only if it is used on at least 5% of the maps of the type, by number of maps desc.

```json
{
  "maps": 42,
  "with_path": 0,
  "backgrounds": [OverviewSprite],
  "grounds": [OverviewSprite],
  "paths": [OverviewSprite]
}
```

| Field | Type | Description |
|---|---|---|
| `maps` | int | Classified maps of the type in the area |
| `with_path` | int | Those with a preferred path |
| `backgrounds` | object[] | Backgrounds of the maps. With a large `bare_share`, the background is the terrain itself; with a small one, it only fills the holes of the ground tiles |
| `grounds` | object[] | Base ground tiles: grounds covering at least 10% of the ground cells of their maps (`ground_share`), transitions excluded. At most 8 |
| `paths` | object[] | Sprites drawing the paths, roads and streets, on the ground or on layer 1: classified in the `path` category, or lying on the preferred path of the maps (`path_lift` of at least 2). A transition (`transition: true`) is a path border |

Many areas have no preferred path at all (Neutral Pandala: none of its 249 maps), so the paths are mostly found by their category, and their use on the maps of the type tells which ones belong there: in Neutral Pandala, the cobblestone patch `o991` is on 81% of the road maps, the pale stone paving `o2250` on none, but on 28% of the villages.

### OverviewSprite

```json
{
  "set": "objects",
  "id": 991,
  "layer": "layer1",
  "name": "cobblestone patch",
  "category": "path",
  "transition": false,
  "maps": 34,
  "share": 0.8095,
  "per_map": 12.35,
  "ground_share": null,
  "bare_share": null,
  "path_lift": null
}
```

| Field | Type | Description |
|---|---|---|
| `set`, `id` | string, int | Sprite set (`grounds` or `objects`) and number |
| `layer` | string | Main layer of the sprite in the area |
| `name`, `category`, `transition` | string, string, bool \| null | Global type of the sprite (see [Type](objects.md#type)). null if the sprite is not classified |
| `maps` | int | Maps of the type using the sprite |
| `share` | float | `maps` / number of maps of the type, from 0 to 1 with 4 decimals |
| `per_map` | float | Average number of uses on a map using it, with 2 decimals |
| `ground_share` | float \| null | For a ground: average share of the ground cells of these maps it covers |
| `bare_share` | float \| null | For a background: average share of the active cells without ground of these maps |
| `path_lift` | float \| null | How much more often than an average sprite its cells lie on the preferred path or touch it, on these maps: 1 is the average. null without 20 cells on maps with a preferred path |

## Sprites

The usage of a sprite on the maps of the area: the same statistics as the global profile ([objects.json](objects.md#profile-fields)), on the maps of the area only, and broken down by location type. The neighbors and the type stay global: the neighbors describe how a sprite is assembled, which hardly depends on the area, and would make these files five times larger.

```json
{
  "layer": "layer1",
  "occurrences": 790,
  "maps": 97,
  "classified_maps": 97,
  "biomes": {"jungle": {"maps": 90, "occurrences": 742}},
  "movements": {"0": 0.12, "4": 0.88},
  "ground_share": null,
  "bare_share": null,
  "path": null,
  "cover_share": 0.18,
  "location_types": {
    "road": {"maps": 34, "occurrences": 420, "ground_share": null, "bare_share": null, "path": null, "cover_share": 0.21}
  }
}
```

| Field | Type | Description |
|---|---|---|
| `layer` | string | Main layer in the area |
| `occurrences`, `maps`, `classified_maps` | int | As in [objects.json](objects.md#profile-fields), on the maps of the area |
| `biomes` | object | Usage by biome (`{"maps", "occurrences"}`), complete |
| `movements`, `ground_share`, `bare_share`, `path`, `cover_share` | | As in [objects.json](objects.md#profile-fields), on the maps of the area |
| `location_types` | object | Usage on the classified maps of each location type: `maps`, `occurrences`, and `ground_share`, `bare_share`, `path`, `cover_share` on these maps |
