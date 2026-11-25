# data/objects.json

Profile and classification of each sprite (graphic) used by the maps of the Dofus Retro 1.29 client. Copy of `var/sprites.json`, produced by `make sprites`. The profile comes from `sprites:analyze`, the classification from `sprites:classify`, and the export from `sprites:export`.

- The profile describes how the sprite is used on the maps: layer, number of uses, location, biomes, neighbors, etc.
- The classification (`type`) is done by the OpenAI model `gpt-6-luna`, from the sprite image, an example map where the sprite is framed in magenta, and the profile.
- 4,774 sprites: 541 grounds and 4,233 objects. All are classified except 5, whose image is empty or missing from the client.

## Structure

Sprites are split into two independent sets, each indexed by sprite number (string key). The same number can name two different sprites, one in each set.

```json
{
  "grounds": {"1": Sprite, "2": Sprite},
  "objects": {"1": Sprite, "2": Sprite}
}
```

| Set | Client files | Layers |
|---|---|---|
| `grounds` | `clips/gfx/g*.swf` | `background`, `ground` |
| `objects` | `clips/gfx/o*.swf` | `layer1`, `layer2` |

In the gallery and in the model context, a sprite is written as the first letter of its set followed by its number: `g69` (ground 69), `o345` (object 345).

### Sprite

```json
{
  "id": 1,
  "layer": "layer2",
  "slope": false,
  "occurrences": 56,
  "maps": 43,
  "classified_maps": 43,
  "areas": [{"id": 7, "name": "Bonta", "maps": 43, "occurrences": 56}],
  "sub_areas": [
    {"id": 37, "name": "Bonta", "maps": 23, "occurrences": 30},
    {"id": 44, "name": "Bakers' Quarter", "maps": 4, "occurrences": 6}
  ],
  "biomes": {"plain": {"maps": 40, "occurrences": 51}},
  "indoor": {"outdoor": {"maps": 43, "occurrences": 56}},
  "location_types": {"city": {"maps": 42, "occurrences": 53}},
  "movements": {"0": 1},
  "ground_share": null,
  "bare_share": null,
  "cover_share": 0.0712,
  "path": {"cells": 55, "distances": {"0": 0, "1": 0, "2": 0.1273}},
  "shore": null,
  "example": {"map": 4172, "cell": 292, "rotation": 0, "flip": false},
  "neighbors": [
    {
      "rotation": 0,
      "flip": false,
      "cells": 49,
      "neighbors": [
        {"x": 0, "y": 1, "id": 6724, "rotation": 0, "flip": false, "count": 35, "frequency": 0.7143},
        {"x": 2, "y": 0, "id": 2, "rotation": 0, "flip": false, "count": 5, "frequency": 0.102}
      ]
    }
  ],
  "interactive": null,
  "image": {"image": "objects/1.png", "width": 195.1, "height": 264.5, "frames": 1},
  "example_image": "objects/1-4172-292.png",
  "type": {
    "category": "building",
    "name": "green-roofed shop",
    "keywords": ["shop", "house", "timber", "wood", "green", "shingles", "stone base", "door"],
    "description": "A compact timber shop with a steep green-shingled roof, a small doorway, and a stone foundation.",
    "transition": false,
    "usage": "building",
    "orientation": "bottom_left"
  }
}
```

## Profile fields

All the decodable maps of the client are taken into account, even the ones that are not classified.

| Field | Type | Description |
|---|---|---|
| `id` | int | Sprite number in its set |
| `layer` | string | Main layer: the one where the sprite is used the most, in number of cells (see [Layers](#layers)) |
| `slope` | bool | true for a ground sprite drawn as a slope. It is placed on sloped cells and has one frame per slope value (15 frames). Only 4 sprites: `g4`, `g6`, `g49` and `g69` |
| `occurrences` | int | Total number of uses: cells, plus one per map for the background |
| `maps` | int | Number of maps using the sprite |
| `classified_maps` | int | Number of these maps successfully classified (see [maps.md](maps.md)) |
| `areas` | object[] | Areas of the maps using the sprite (see [Distributions](#distributions)) |
| `sub_areas` | object[] | Sub areas of the maps using the sprite (see [Distributions](#distributions)) |
| `biomes` | object | Biomes of the maps: the map biome, or else the biome of its sub area. Keys: see [maps.md](maps.md#biomes) |
| `indoor` | object | Keys `indoor` / `outdoor`, from the classified maps only |
| `location_types` | object | Location types of the classified maps. Keys: see [maps.md](maps.md#location-types) |
| `movements` | object | Frequency of each movement value of the cells using the sprite (see [Movements](#movements)) |
| `ground_share` | float \| null | For a sprite whose main layer is `ground`: average share of the ground cells of its maps that it covers, from 0 to 1 with 4 decimals. A base tile covers a large share of its maps, a variant only a few cells. null for the other layers |
| `bare_share` | float \| null | For a sprite whose main layer is `background`: average share of the active cells without ground of its maps, from 0 to 1 with 4 decimals. A background visible on most of the map is the terrain itself (e.g. the grass `g71`), a background hidden under ground tiles only fills the holes. null for the other layers |
| `cover_share` | float \| null | For a sprite used on `layer1` or `layer2`: average share of the cells of its maps lying under it (their centre inside the sprite, on any of its uses), from 0 to 1 with 4 decimals. A large patch of grass repeated over the map covers most of it (the yellow grass `o3745` of Incarnam), a small flower a cell or two. null for a ground or a background, which covers its own cell or the whole map |
| `path` | object \| null | Position of the cells relative to the preferred path of the maps (see [Path](#path)). null if the sprite is never used on a map with a preferred path |
| `shore` | object \| null | For a sprite used on `layer1` or `layer2`: position of its cells relative to the water of the maps (see [Shore](#shore)). null for a ground, a background, or an object never used on a map with water |
| `example` | object \| null | Use shown to the model as an example (see [Example](#example)). null for a background |
| `neighbors` | object[] | Frequent neighbors on the same layer, to find composed objects (see [Neighbors](#neighbors)) |
| `interactive` | object \| null | Interactive object drawn by the sprite, from the game langs (see [Interactive objects](#interactive-objects)). null for non-interactive sprites |
| `image` | object \| null | Sprite image (see [Images](#images)). null if the sprite is empty or missing from the client |
| `example_image` | string \| null | Path of the example map with the framed sprite, relative to `var/sprite_examples` (`{set}/{id}-{map}-{cell}.png`). null if there is no example or if it could not be rendered |
| `type` | object \| null | Classification by the model (see [Type](#type)). null if the sprite is not classified |

### Layers

| Value | Set | Description |
|---|---|---|
| `background` | grounds | Background image of the map, not placed on a cell (43 sprites) |
| `ground` | grounds | Floor of a cell: terrain tile |
| `layer1` | objects | Element drawn flat on the ground, below characters: paths, carpets, shadows, small vegetation, ground details |
| `layer2` | objects | Element standing on the ground, drawn above it: trees, walls, buildings, furniture, objects |

### Distributions

`areas` and `sub_areas` are lists of `{"id", "name", "maps", "occurrences"}` objects, sorted by number of maps desc. `name` is null if the area is missing from the lang.

`biomes`, `indoor` and `location_types` are indexed by value: `{value: {"maps", "occurrences"}}`, sorted by number of maps desc.

- `maps`: number of maps using the sprite with this value.
- `occurrences`: total number of uses of the sprite on these maps.

Rare values are ignored as noise: a value is kept only if its number of maps is at least 10% of the main value's.

### Movements

`movements` is indexed by the `movement` value of the cell (string key) and gives the frequency of each value, from 0 to 1, with 6 decimals. The sum is 1. The object is empty for a sprite used only as background.

| Value | Description |
|---|---|
| `0` | Not walkable (blocked) cell |
| `1` | Interactive object |
| `2` to `7` | Walkable cell; the higher the value, the more the path is preferred |

### Path

The cells with a preferred movement (`5` to `7`, on active cells) draw the path the characters walk on: a line of one cell wide, placed by hand on the middle of the roads, streets and bridges. It is precise (the line is almost always on a drawn road) but often incomplete, and only about 15% of the maps have one (40% of the `road` maps, 60% of the `city` maps, almost no `indoor` map).

`path` tells where the cells of the sprite are relative to this line, on the maps that have one:

- `cells`: number of cells using the sprite on these maps.
- `distances`: frequency, from 0 to 1 with 4 decimals, of the cells at each distance from the line (string key `0` to `2`): `0` on the line, `1` touching it (including by a corner), `2` one cell away. The distance is counted in cells on the grid, diagonals included. The remainder (1 minus the sum) is the frequency of the cells farther away.

Compare with all the cells of these maps: about 15% at `0`, 12% at `1`, 8% at `2` and 65% farther. A road tile is mostly at `0` (e.g. the Amakna dirt road `g10`: 70%), a road border or a decoration along the road mostly at `1` (e.g. `o120`: 8% at `0`, but 83% within 1 cell). With few `cells`, the frequencies are not reliable.

### Shore

A water cell shows water: a ground classified as `water` (see [Type](#type)), or no ground over a background classified as `water`. On the maps with at least one water cell, `shore` tells where the cells of the object are relative to the water:

- `cells`: number of cells using the object on these maps.
- `water_share`: share of these cells that are water cells, from 0 to 1 with 4 decimals.
- `fills`: frequency of the water sprites (ground id, or the background id) under the object, on its water cells.
- `land`: frequency of the land around its water cells, by the directions of the land cells among the 8 around: `n` (the cell above, two half rows up), `ne`, `e` (the next cell of the row), `se`, `s`, `sw`, `w`, `nw`. `ne`, `se`, `sw` and `nw` share an edge with the cell, `n`, `e`, `s` and `w` only a point. The directions are listed in this order, separated by a space; `""` is open water. The cells outside the map are not counted as land. Values below 2% are removed.
- `coast_share`: share of these cells that are land cells with water among the 8 cells around: the object lies along the water, on the land side.
- `water`: frequency of the water around these land cells, by the directions of the water cells, as for `land`. Values below 2% are removed.

Frequencies have 4 decimals, sorted by frequency. A lily pad lies on open water (`o226`: `water_share` 1, 68% `""`). A shore piece lies on the water cells along the land, and the land around them tells which edge of the water it draws, which the model cannot see on the sprite alone. The grassy cliffs of Astrub and Wabbit Island are drawn under the land, on the water cells of its front edges (`o377`: land `n ne nw`; `o379`: `n w nw`), and the back edges are covered by grass edges on the water that the model takes for tufts (`o374`: `e se s`, `o375`: `s sw w`, `o376`: `s`). The riverbanks of Amakna (`o804` to `o815`) lie on the land cells instead (`water_share` 0): they draw the water over the land, around the corners of the cells. Some cliffs are anchored on the land too: around the lake of Astrub (maps 7380, 7381), the cliffs lie on its last land cells, the water below them (`o377`: `water` `se s sw`), and its right flank `o382` has the water on its west (`n sw w nw`).

### Example

`example` is a use of the sprite on its main layer, shown to the model as an example. It is chosen this way:

- **Map**: the map where the sprite is used the most, to see it in its usual context (e.g. a rampart with its other sections).
- **Cell**: on this map, the cell closest to the center, preferring a use that is neither rotated nor flipped.

| Field | Type | Description |
|---|---|---|
| `map` | int | Map id |
| `cell` | int | Cell id |
| `rotation` | int | Rotation of the sprite on the cell, in quarter turns (0 to 3) |
| `flip` | bool | true if the sprite is flipped (horizontal mirror) on the cell. In this case, `example_image` is mirrored, to show the sprite in the same direction as on its own image |

### Neighbors

`neighbors` gives the sprites frequently found around the sprite, on the same layer. It is used to find objects made of several sprites (ramparts, fences, buildings, grounds made of several tiles…) and to generate them procedurally.

- It is computed on the main layer only. It is empty for a sprite used only as background.
- It has one entry per orientation of the sprite, sorted by number of cells desc.

| Field | Type | Description |
|---|---|---|
| `rotation` | int | Rotation of the sprite, in quarter turns (0 to 3) |
| `flip` | bool | true if the sprite is flipped |
| `cells` | int | Number of cells using the sprite with this orientation |
| `neighbors` | object[] | Frequent neighbors, sorted by `count` desc (see below) |

Each neighbor:

| Field | Type | Description |
|---|---|---|
| `x`, `y` | int | Offset of the neighbor, in game cell coordinates (from -4 to 4) |
| `id` | int | Number of the neighbor sprite, in the same set. It can be the sprite itself (repeated pattern) |
| `rotation`, `flip` | int, bool | Orientation of the neighbor sprite |
| `count` | int | Number of cells of the sprite having this neighbor at this offset |
| `frequency` | float | `count` / `cells`, with 4 decimals |

**Offset**: `x` points to the south-east, i.e. cell id + map width. `y` points to the south-west, i.e. cell id + width − 1. The next cell on the same row is therefore at `x = 1, y = -1`.

**Range**: neighbors are searched up to 4 cells on each axis, i.e. 81 cells around. Big sprites (Astrub ramparts…) cover several cells and are repeated every 2 to 4 cells.

**Filter**: a neighbor is kept only if it is found at least 2 times and on at least 10% of the cells.

**Orientation**: offsets are absolute. A flipped sprite has the neighbors of its unflipped version, mirrored.

### Interactive objects

`interactive` is `{"id", "name", "type"}`:

- `id`: id of the interactive object in the lang.
- `name`: name of the object, in English.
- `type`: type of the object (see the table). Types have no label in the langs: they are named after their objects.

About 176 sprites are concerned, all on `layer2`.

| Value | Description |
|---|---|
| `resource` | Resource harvested by a profession: tree, ore, cereal, plant, fish… |
| `workshop` | Workshop used by a profession to craft items |
| `zaap` | Teleportation portal |
| `fountain` | Fountain giving life |
| `door` | Door of a house or building |
| `storage` | Safe or trash |
| `pot` | Pot |
| `zaapi` | Teleportation post inside a city |
| `craftsmen list` | Board listing the craftsmen |
| `paddock` | Paddock for mounts |
| `switch` | Switch, lever |
| `class statue` | Class statue |
| `other` | Unknown type |

### Images

`image` is `{"image", "width", "height", "frames"}`:

- `image`: path of the PNG sent to the model, relative to `var/sprites` (`{set}/{id}.png`). The PNG is cropped, scaled up to about 512 px, on a grey `#a0a0a0` background. For a sloped sprite, it is the flat ground frame.
- `width`, `height`: visible size of the sprite in game pixels. A cell is 53 × 27 pixels.
- `frames`: number of frames of the sprite (animation, or one frame per slope).

The images are not in the repository: they can be generated again with `make sprites`.

## Type

Classification of the sprite by the model.

| Field | Type | Description |
|---|---|---|
| `category` | string | Category, closed list (see [Categories](#categories)) |
| `name` | string | Short free name (e.g. `pine tree`). For an interactive object, it is the name of the object from the lang, which replaces the model's |
| `keywords` | string[] | Free keywords describing the sprite, without biome or place. For an interactive object, they also include the name given by the model and the interactive object type |
| `description` | string | Short description |
| `transition` | bool \| null | true if the sprite is a transition from one environment or material to another (water to grass, path border, cliff edge…). false for a sprite of a single environment or material. true for 283 sprites |
| `usage` | string \| null | How the sprite is used to build the maps, depending on its main layer (see [Usages](#usages)). null for a background |
| `orientation` | string \| null | Direction the sprite points to as drawn by default, neither flipped nor rotated (see [Orientations](#orientations)). null if the sprite has no clear orientation |

`transition`, `usage` and `orientation` are also null for types classified before these fields were added. No sprite of this file is in this case.

### Categories

| Value | Description |
|---|---|
| `tree` | Tree, palm tree, dead tree, tree trunk or stump |
| `bush` | Bush, shrub, hedge |
| `grass` | Grass tuft, tall grass, reeds |
| `flower` | Flowers, flower bed |
| `plant` | Other vegetation: fern, vine, bamboo, cactus, aquatic plant, moss |
| `crop` | Cultivated plants: wheat, cereals, vegetables |
| `mushroom` | Mushrooms |
| `rock` | Rock, boulder, stones, pebbles, crystal |
| `cliff` | Cliff, rock wall, relief edge, chasm border |
| `water` | Water surface, waterfall, lava, puddle, water edge |
| `ground` | Natural ground tile: grass, dirt, sand, snow, mud |
| `path` | Outdoor path, road, paving, stepping stones |
| `floor` | Indoor floor tile: planks, tiles, carpet |
| `building` | Whole building or large part of it: house, tower, tent, hut |
| `wall` | Wall, rampart, wall section |
| `roof` | Roof or roof part |
| `door` | Door, gate, portal |
| `window` | Window |
| `stairs` | Stairs, ladder, steps |
| `bridge` | Bridge, walkway, pontoon |
| `fence` | Fence, barrier, railing, palisade |
| `furniture` | Table, chair, bed, shelf, counter, carpet |
| `container` | Chest, barrel, crate, bag, jar, basket |
| `light` | Lamp, torch, candle, lantern, brazier, fire |
| `statue` | Statue, monument, totem, pillar, column |
| `tomb` | Tombstone, grave, cross, coffin |
| `sign` | Signpost, banner, flag, poster |
| `tool` | Tool, weapon, workshop equipment, anvil, well |
| `vehicle` | Boat, cart, wagon |
| `decoration` | Other small decorative object: pottery, books, bottles, food |
| `remains` | Bones, skull, debris, ruins, rubble |
| `creature` | Static animal or character drawn in the decor |
| `shadow` | Shadow or dark spot only |
| `effect` | Visual effect: light rays, fog, sparkles, smoke, glow |
| `marker` | Game marker or technical sprite: cell marker, teleport marker, invisible or abstract shape |
| `other` | None of the above |

### Usages

The possible values depend on the main layer of the sprite (`layer`).

| Layer | Value | Description |
|---|---|---|
| `ground` | `fill` | Base tile repeated to cover an area: grass, sand, dirt, water, wooden floor, paving. Includes the tiles of a pattern drawn on several cells (carpet, mosaic) and the transition tiles |
| `ground` | `variation` | Variant of a base tile with a detail (flowers, cracks, stains, small stones…), placed sparsely to break the repetition |
| `layer1` | `ground` | Detail painted on the ground, part of the floor surface: path, road, grass or dirt patch, carpet, puddle, ground border or pattern |
| `layer1` | `small_object` | Small object lying on the ground: stones, pebbles, small plants and flowers, bones, leaves |
| `layer1` | `shadow` | Shadow or light spot cast on the ground, usually under or next to another element |
| `layer2` | `big_object` | Large standalone element: tree, big rock, statue, big furniture, well, cart, workshop, resource |
| `layer2` | `wall` | Part of a linear barrier repeated along a line: wall, rampart, fence, palisade, hedge, cliff side, railing |
| `layer2` | `building` | Part of a building: house or tower wall, roof, door, window, stairs, pillar, arch |
| `layer2` | `decoration` | Small decoration placed on or next to other elements: small plant, lamp, sign, pot, book, tableware, small furniture, wall hanging |
| `background` | — | Always null |

For a ground, water is a `fill`: `movements` tells it apart. A border between two grounds is found with `transition`.

### Orientations

Directions are read on the screen: `top`, `top_right`, `right`, `bottom_right`, `bottom`, `bottom_left`, `left`, `top_left`. The value depends on the sprite:

- **Transition**: direction of the other environment or material, the sprite being mostly made of the first one. For example, a water tile with grass at the bottom is `bottom`.
- **Other sprites**: direction of use, for example the door of a house, the seat of a chair, the front of a statue or a sign, the visible side of a wall.
- **null**: symmetric or round element, plain tile, tree, rock.

A sprite flipped on a cell (`flip`) has the mirrored orientation: left and right are swapped, `top` and `bottom` do not change. For example, `bottom_left` becomes `bottom_right`.
