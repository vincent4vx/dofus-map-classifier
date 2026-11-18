# data/maps.json

Classification of each map of the Dofus Retro 1.29 client, with its location. Copy of `var/maps.json`, produced by `make export` (`export` command).

- 9,209 maps: 8,473 classified (`status: ok`) and 736 in error (735 without a decryption key, 1 classification failure).
- The maps were classified by the OpenAI model `gpt-6-luna`, from the rendering of the map.

## Structure

Object indexed by map id (string key):

```json
{
  "4": {
    "id": 4,
    "version": "0706131721",
    "location": {
      "sub_area": {"id": 33, "name": "Treechnid Forest", "biome": "forest"},
      "area": {"id": 6, "name": "Treechnid Forest"},
      "super_area": {"id": 0, "name": "Amaknean Continent"}
    },
    "status": "ok",
    "error": null,
    "error_message": null,
    "biome": "cave",
    "biome_other": null,
    "indoor": true,
    "location_type": "dungeon",
    "tags": ["rocky floor", "cave walls", "bones", "skulls", "wooden supports", "grass tufts", "stone doorway"],
    "description": "A dark rocky chamber with wooden supports, scattered bones and skulls, and sparse tufts of grass. A shadowy doorway opens in the rear wall.",
    "model": "gpt-6-luna",
    "classified_at": "2026-11-12T19:11:34+01:00"
  }
}
```

## Fields

| Field | Type | Description |
|---|---|---|
| `id` | int | Map id |
| `version` | string | Version (date) of the map file in the client (`data/maps/{id}_{version}X.swf`) |
| `location` | object \| null | Location from the game langs (see below). null if the map has no sub area in the lang (31 maps) |
| `status` | string | `ok`: classified map; `error`: map not classified (see `error`) |
| `error` | string \| null | Error code, null if `status = ok` (see [Error codes](#error-codes)) |
| `error_message` | string \| null | Detailed error message, null if `status = ok` |
| `biome` | string \| null | Natural environment of the map (see [Biomes](#biomes)). null on error, or if the map shows no natural environment (most indoor maps): use `location.sub_area.biome` instead |
| `biome_other` | string \| null | Free label of the biome if `biome = other`, null otherwise |
| `indoor` | bool \| null | true for an indoor map (house, shop, cave, closed dungeon…), false for an outdoor map; null on error |
| `location_type` | string \| null | Kind of place (see [Location types](#location-types)); null on error |
| `tags` | string[] | Free keywords describing the visible elements; empty on error |
| `description` | string \| null | Short description of the map; null on error |
| `model` | string \| null | Model used for the classification; null if the map could not be rendered |
| `classified_at` | string | Date of the classification (or of the attempt), ISO 8601 |

### location

| Field | Type | Description |
|---|---|---|
| `sub_area.id`, `sub_area.name` | int, string | Sub area of the map |
| `sub_area.biome` | string \| null | Biome of the sub area (see [sub_areas.md](sub_areas.md)): most frequent biome of its classified maps. null if none of its maps has a biome |
| `area.id`, `area.name` | int, string | Area of the sub area |
| `super_area.id`, `super_area.name` | int, string | Continent of the area |

Names come from the English langs of the game.

## Possible values

### Biomes

| Value | Description |
|---|---|
| `forest` | Woods: many trees spread over the map (even small trees, even with grass between them) |
| `jungle` | Tropical vegetation, palm trees, lianas |
| `plain` | Open grassland, meadows: grass with only a few isolated trees (around 5 or less) |
| `field` | Cultivated land: crops, wheat, farmland |
| `desert` | Sand, dunes, arid land |
| `mountain` | Rocky relief inside the map: rocky cliffs, stone walls, rocky slopes, boulders, mountain paths |
| `swamp` | Marsh or bog: stagnant water (murky, brown or green) covering a significant part of the ground, mangroves |
| `snow` | Snow or ice covered land |
| `beach` | Sandy shore next to the sea |
| `sea` | Mostly open water, islands, boats |
| `lake_river` | Land mostly structured around a lake or river |
| `cave` | Natural cave, rock walls in darkness |
| `underground` | Man-made underground: mine, sewer, crypt, cellar |
| `volcanic` | Lava, volcanic rocks, ashes |
| `wasteland` | Dead, dry, corrupted or desolate land: dead trees, dry grass, cracked ground |
| `cemetery` | Graveyard: tombstones, graves, crosses, tombs |
| `other` | Natural environment not listed above, detailed in `biome_other` |

### Location types

| Value | Description |
|---|---|
| `city` | Streets and buildings of a large town |
| `village` | A few houses, small settlement |
| `road` | Path or road crossing a mostly natural area |
| `wild` | Natural area without notable construction |
| `dungeon` | Hostile closed area, lair, monster den |
| `house` | Inside of a house or private building |
| `shop` | Inside of a shop, workshop, tavern or bank |
| `temple` | Temple, sanctuary, sacred place |
| `mine` | Mine galleries, quarry |
| `arena` | Arena or area dedicated to fights |
| `other` | None of the above |

### Error codes

| Value | Description |
|---|---|
| `missing_key` | The map is encrypted and its key is not available for this version |
| `render_failed` | The map cannot be parsed or rendered |
| `classification_failed` | The model or the API returned an error or an invalid response |
| `refused` | The model refused to classify the map |
