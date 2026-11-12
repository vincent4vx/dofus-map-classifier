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

## Usage

```bash
# 1. Download map keys (MapKeys-DR)
make console ARGS="keys:download"

# 2. Render maps to var/renders/{id}.png (~0.2s per map)
make console ARGS="maps:render"              # all maps
make console ARGS="maps:render 10000-10100"  # ids or ranges

# 3. Submit to the OpenAI Batch API (asks confirmation)
make console ARGS="classify:submit --limit=20"
make console ARGS="classify:submit --dry-run"   # only build var/batches/input/*.jsonl

# 3bis. Or classify synchronously (Responses API, parallel requests, retry on 429/5xx)
make console ARGS="classify:run 10000-10100 --concurrency=5"

# 4. Follow and import (batches complete within 24h)
make console ARGS="classify:status"
make console ARGS="classify:import"

# 5. Export / visual check
make console ARGS="export"                  # var/maps.json
make console ARGS="report --biome=forest"   # var/report.html
```

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
  maps.json                   export
  report.html                 visual report
```
