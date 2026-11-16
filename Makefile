COMPOSE = DOCKER_UID=$(shell id -u) DOCKER_GID=$(shell id -g) docker compose
RUN = $(COMPOSE) run --rm php
CONSOLE = $(RUN) bin/console

# Extra arguments passed to the command, e.g. make render ARGS="--super-area=3"
ARGS ?=

.PHONY: help build shell install test phpstan client console keys render classify submit status import discard export report objects objects-export objects-classify pipeline

help: ## Display this help
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  \033[36m%-17s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Pass arguments with ARGS, e.g.:"
	@echo "  make render ARGS=\"--super-area=3\""
	@echo "  make classify ARGS=\"10000-10100 --concurrency=10\""
	@echo "  make report ARGS=\"--area=18\""

## Setup

build: ## Build the docker image
	$(COMPOSE) build

install: build ## Install composer dependencies
	$(RUN) composer install

shell: ## Open a shell in the container
	$(RUN) bash

test: ## Run the tests
	$(RUN) vendor/bin/phpunit $(ARGS)

phpstan: ## Run the static analysis
	$(RUN) vendor/bin/phpstan analyse $(ARGS)

console: ## Run any console command: make console ARGS="list"
	$(CONSOLE) $(ARGS)

## Workflow

client: ## Download a Dofus Retro client from dofedex into var/dofus (maps and sprites only, --full for all)
	$(CONSOLE) client:download $(ARGS)

keys: ## Download map keys (MapKeys-DR)
	$(CONSOLE) keys:download $(ARGS)

render: ## Render maps to PNG (ids, --super-area, --area, --sub-area, --limit, --force)
	$(CONSOLE) maps:render $(ARGS)

classify: ## Classify rendered maps synchronously (ids, --concurrency, --force, --retry-errors…)
	$(CONSOLE) classify:run $(ARGS)

submit: ## Submit rendered maps to the Batch API (ids, --limit, --dry-run…)
	$(CONSOLE) classify:submit $(ARGS)

status: ## Display the state of submitted batches (--all)
	$(CONSOLE) classify:status $(ARGS)

import: ## Import the results of terminated batches
	$(CONSOLE) classify:import $(ARGS)

discard: ## Discard batches, their results will never be imported: make discard ARGS="batch_xxx"
	$(CONSOLE) classify:discard $(ARGS)

export: ## Export results to var/maps.json and var/sub_areas.json (--only-ok)
	$(CONSOLE) export $(ARGS)

report: ## Generate the HTML report var/report.html (ids, --area, --biome…)
	$(CONSOLE) report $(ARGS)

objects-classify: ## Find the type of sprites, export them to var/objects.json and regenerate var/objects.html (ids like o345 g10-20, --set, --limit…)
	$(CONSOLE) objects:classify $(ARGS)
	$(CONSOLE) objects:report

objects: ## Analyze sprites (var/sprite_profiles.json), render them to PNG, export var/objects.json and generate var/objects.html (selection, --min-ratio)
	$(CONSOLE) objects:analyze $(ARGS)
	$(CONSOLE) objects:sprites
	$(CONSOLE) objects:export
	$(CONSOLE) objects:report

objects-export: ## Export sprites profiles with their type to var/objects.json
	$(CONSOLE) objects:export $(ARGS)

pipeline: ## Render, classify synchronously, export and report the selection: make pipeline ARGS="--area=18"
	$(CONSOLE) maps:render $(ARGS)
	$(CONSOLE) classify:run $(ARGS)
	$(CONSOLE) export
	$(CONSOLE) report
