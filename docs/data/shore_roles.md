# Shore roles (`shore_roles/o{id}.json`)

The role of each shore piece in the shores of the maps, as the model sees it on an example along the water. Written by `shores:classify`, from the examples of `shores:examples`.

**Shore pieces** are the `layer1` objects whose `shore` (see [objects.md](objects.md#shore)) shows them on water at least 25% of the time on the maps with water, and on open water less than half of it: cliffs, grass edges, and the pieces turning or ending them. The LLM types cannot tell them: some are typed as grass tufts.

**Examples** (`shore_examples/o{id}.png`): for each piece, the map where it borders the water the most (upright, on water cells with land around), cropped 3 cells around its use the closest to the center. Only the background, the ground and the shore pieces are drawn: the trees, buildings and objects standing over the shore are removed. The piece is framed in magenta. A piece never used upright along the water has no example, and is not classified.

**Roles**: the model receives the image of the piece, its example, and the land observed around it on the native maps.

```json
{"id": 377, "model": "gpt-6-luna", "role": {"kind": "cliff", "shape": "straight", "water": ["s"], "land": ["n"], "continues": ["e", "w"], "description": "A straight cliff face borders water below the grassy land."}, "error": null}
```

| Field | Type | Description |
|---|---|---|
| `kind` | string | What the piece draws: `cliff` (the land stands above the water, with an earthen face going down to it), `grass_edge` (a grassy rim over the water at the foot of the land), `other` |
| `shape` | string | Where it lies along the shore: `straight` (a section repeated along a straight shore), `outer_corner` (the shore turns around a convex corner of the land), `inner_corner` (around a convex corner of the water), `end` (the shore stops or changes kind), `other` |
| `water` | string[] | Directions of the water around the piece: `n` (up), `ne`, `e`, `se`, `s`, `sw`, `w`, `nw` |
| `land` | string[] | Directions of the land around the piece |
| `continues` | string[] | Directions in which the shore goes on beyond the piece |
| `description` | string | One short sentence |

`role` is null and `error` gives the reason when the piece could not be classified.

The kind is reliable, the shape mostly: on the cliffs of Astrub and Wabbit Island (`o374` to `o383`), all the kinds and the corners match the native maps. The directions in which the shore goes on are not: the model reads the fine isometric geometry badly (4 of these 10 pieces), and two runs may differ. The native maps give them reliably: the offsets at which a piece repeats itself (`neighbors`) and the land around it (`shore`).
