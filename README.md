# Map Classifier

Automatic classification of Dofus Retro maps (biome, indoor, location type, tags, description)
using [php-map-parser](https://github.com/Arakne/php-map-parser) for rendering and the OpenAI Responses API for classification.

See [docs/SPEC.md](docs/SPEC.md) for the specifications.

## Setup

Requirements: Docker, and a Dofus Retro client. Without a client, `make client` downloads one from
[dofedex](https://github.com/dofera/dofedex) (only the maps and sprites, ~80 MB) into `var/dofus`:
set `DOFUS_CLIENT_DIR=./var/dofus` in `.env` to use it.

```bash
cp .env.dist .env   # fill OPENAI_API_KEY, OPENAI_MODEL and DOFUS_CLIENT_DIR
make install
make client        # optional: download a client into var/dofus (also used by the tests)
make test
make phpstan        # static analysis (level 8)
```

The tests using the client (rendering, sprites, whole workflow) run with `DOFUS_PATH`, or with the client
downloaded in `var/dofus`; they are skipped when no client is available. The CI downloads it with `client:download`.

Environment variables (container side):

| Variable | Default | Description |
|---|---|---|
| `DOFUS_PATH` | `/dofus` | Dofus client path (mounted from `DOFUS_CLIENT_DIR`) |
| `CLIENT_ARCHIVE_URL` | dofedex 1.41.7 classic | Client zip archive downloaded by `client:download` |
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
make classify ARGS="--super-area=3 --concurrency=10"   # 3. classify with the OpenAI Responses API
make export                                       # 4. var/maps.json + var/sub_areas.json + var/report.html (search and filters)
make report ARGS="--area=18"                      # 5. only var/report.html, for a selection (= export --format=html)
make sprites                                      # 6. var/sprite_profiles.json (layer, areas, biomes… of each sprite), PNG sprites,
                                                  #    var/sprites.json export and var/sprites.html
make sprites-classify ARGS="--limit=500"          # 7. type of each sprite (category, name, keywords), most used first,
                                                  #    exported to var/sprites.json and displayed in var/sprites.html
                                                  #    --interactive: only interactive objects (resources, workshops, doors…)

make pipeline ARGS="--area=18"                    # render + classify + export of a selection
```

Maps selection (render, classify, export): ids or ranges (`10000 100-200`), `--super-area`, `--area`, `--sub-area`, `--limit`.
Exports (`export`, `sprites:export`) write all formats by default: `--format=json` or `--format=html` for a single one, with `--output` to change its file.
`make pipeline` only accepts selection arguments.

All commands are resumable: rendered / classified maps are skipped.
Use `--force` to process them again, `--retry-errors` to retry maps in error.

## Data layout

```
var/
  keys/{id}_{version}.txt     map keys
  renders/{id}.png            rendered maps
  results/{id}.json           result per map (classification or error)
  cache/sprites.db            rendering sprite cache
  maps.json                   export of maps, with location (sub area, area, super area)
  sub_areas.json              biome of each sub area, deduced from its classified maps
  sprite_profiles.json        data of each sprite (layer, areas, biomes…), aggregated from the maps
  sprites.json                export of sprites: profile and type of each sprite
  sprites/{grounds|objects}/  sprites rendered as PNG (as sent to the model), with sizes.json
  sprites.html                visual report of sprites, with filters
  sprite_types/{set}/{id}.json  type of each sprite (category, name, keywords)
  report.html                 visual report
```

## Code organization

Code is split by domain, then by kind of class:

```
src/
  Client/                 download of a Dofus Retro client (dofedex)
  Location/               map locations (sub area, area, super area) from the langs
  Map/                    map files, keys and rendering
    Classification/       biome classification of maps, sub areas biomes, export (JSON and HTML gallery)
  Sprite/                 sprites used by the maps: extraction, aggregated profiles, PNG export
    Classification/       type of sprites (category, name, keywords), export (JSON and HTML gallery)
  Infrastructure/         technical tools: OpenAI client, JSON files, lang files, paths
  Application/            configuration and services wiring (Kernel)
```

Each domain separates the data read from the game files from the `Classification` made by the model
(external API, which costs and may be wrong, and is stored apart in `var/`).

Inside each domain (and each `Classification`):

- `Command/`: console commands of the domain
- `Model/`: data structures (value objects, enums), without dependencies on services or storage. Classes written to JSON files implement `JsonSerializable` and document their JSON format (e.g. `Map/Classification/Model/Export` for `maps.json`)
- `Repository/`: data access (JSON files, lang files, client files)
- `Service/`: processing (rendering, classification, aggregation, selection…)
- `Export/`: export formats (`json`, `html`…), implementing `Infrastructure\Export\ExportFormat`, selected with the `--format` option of the export commands
- `Exception/`: domain errors

Dependencies go one way: `Location` ← `Map` ← `Map\Classification` ← `Sprite` ← `Sprite\Classification`, all of them may use `Infrastructure`.
Only `Application` knows the configuration and wires the services: commands receive their services and paths.
Commands only handle the console input and output, and delegate to the services. Tests follow the same tree.

