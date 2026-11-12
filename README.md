# Map Classifier

Automatic classification of Dofus Retro maps (biome, indoor, location type, tags, description)
using [php-map-parser](https://github.com/Arakne/php-map-parser) for rendering and the OpenAI Batch API for classification.

See [docs/SPEC.md](docs/SPEC.md) for the specifications.

## Setup

Requirements: Docker, and a Dofus 1.29 client.

```bash
cp .env.dist .env   # fill OPENAI_API_KEY, OPENAI_MODEL and DOFUS_CLIENT_DIR
make install
make test
```

Environment variables (container side):

| Variable | Default | Description |
|---|---|---|
| `DOFUS_PATH` | `/dofus` | Dofus client path (mounted from `DOFUS_CLIENT_DIR`) |
| `OPENAI_API_KEY` | | OpenAI API key |
| `OPENAI_MODEL` | `luna` | Model used for classification |
| `OPENAI_REASONING_EFFORT` | | Reasoning effort (`minimal`, `low`…), only for reasoning models |
| `VAR_DIR` | `var/` | Data directory |
| `LANG_DIR` | `/lang` | Lang swf files directory (mounted from `DOFUS_LANG_DIR`) |
| `DOFUS_LANG` | `en` | Lang used for area names |

## Usage

All commands are available through `make` (run `make help`), extra arguments are passed with `ARGS`:

```bash
make keys                                         # 1. download map keys (MapKeys-DR)
make render ARGS="--super-area=3"                 # 2. render maps to var/renders/{id}.png (~0.2s per map)
make classify ARGS="--super-area=3 --concurrency=10"   # 3. classify synchronously (Responses API)
make export                                       # 4. var/maps.json + var/sub_areas.json
make report                                       # 5. var/report.html (search and filters)
make objects                                      # 6. var/objects.json (layer, areas, biomes… of each sprite), PNG sprites, var/objects.html
make objects-classify ARGS="--limit=500"          # 7. type of each sprite (category, name, keywords), most used first
make objects                                      #    then run again to join types in var/objects.json

make pipeline ARGS="--area=18"                    # render + classify + export + report of a selection

# Batch API (cheaper, asynchronous, up to 24h)
make submit ARGS="--limit=1000"                   # asks confirmation, --dry-run to only build files
make status
make import
make discard ARGS="batch_xxx"                   # never import a batch (cancelled, obsolete prompt…)
```

Maps selection (render, classify, submit, report): ids or ranges (`10000 100-200`), `--super-area`, `--area`, `--sub-area`, `--limit`.
`make pipeline` only accepts selection arguments.

All commands are resumable: rendered / classified maps and maps in a pending batch are skipped.
Use `--force` to process them again, `--retry-errors` to retry maps in error.

## Data layout

```
var/
  keys/{id}_{version}.txt     map keys
  renders/{id}.png            rendered maps
  results/{id}.json           result per map (classification or error)
  batches/batch_*.json        submitted batches tracking
  batches/input/*.jsonl       batch input files
  batches/output/*.jsonl      batch output files
  cache/sprites.db            rendering sprite cache
  maps.json                   export of maps, with location (sub area, area, super area)
  sub_areas.json              biome of each sub area, deduced from its classified maps
  objects.json                data of each sprite (layer, areas, biomes…), aggregated from the maps
  sprites/{grounds|objects}/  sprites rendered as PNG (as sent to the model), with sizes.json
  objects.html                visual report of sprites, with filters
  object_types/{set}/{id}.json  type of each sprite (category, name, keywords)
  report.html                 visual report
```
