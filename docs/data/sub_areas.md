# data/sub_areas.json

Biome of each sub area of the game, deduced from its classified maps. Copy of `var/sub_areas.json`, produced by `make export` (`export` command) along with [maps.json](maps.md).

It completes the biome of the maps without one (indoor maps, maps in error): it is the `location.sub_area.biome` value of [maps.json](maps.md).

- 249 sub areas, 27 of them without a biome (none of their maps has a biome).

## Structure

Object indexed by sub area id (string key):

```json
{
  "1": {
    "id": 1,
    "name": "Madrestam Harbour",
    "area": {"id": 0, "name": "Amakna"},
    "biome": "sea",
    "votes": {"sea": 16, "plain": 4, "beach": 4, "desert": 3, "lake_river": 1},
    "outdoor_only": true,
    "total_maps": 68,
    "classified_maps": 66
  }
}
```

## Fields

| Field | Type | Description |
|---|---|---|
| `id` | int | Sub area id |
| `name` | string | Sub area name (English langs of the game) |
| `area` | object \| null | Area of the sub area, `{"id", "name"}`; null if missing from the lang |
| `biome` | string \| null | Most frequent biome in `votes` (on a tie, the first one in the order of the [biomes list](maps.md#biomes)); null if `votes` is empty. Same values as the `biome` of the maps (see [maps.md](maps.md#biomes)) |
| `votes` | object | Number of maps per biome, `{biome: maps}`, sorted by count desc, then in the order of the biomes list. Empty if no map has a biome |
| `outdoor_only` | bool | true if only the outdoor maps vote (the sub area has at least one outdoor map with a biome); false if no outdoor map has a biome: all the classified maps with a biome (indoor) vote instead |
| `total_maps` | int | Number of maps of the sub area in the client |
| `classified_maps` | int | Number of maps of the sub area successfully classified |

Classified maps without a biome (`biome: null`) do not vote.
