COMPOSE = DOCKER_UID=$(shell id -u) DOCKER_GID=$(shell id -g) docker compose
RUN = $(COMPOSE) run --rm php
CONSOLE = $(RUN) bin/console

# Extra arguments passed to the command, e.g. make render ARGS="--super-area=3"
ARGS ?=

.PHONY: help build shell install test phpstan client console keys render classify export report sprites sprites-export sprites-classify pipeline

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

classify: ## Classify rendered maps with the OpenAI Responses API (ids, --concurrency, --force, --retry-errors…)
	$(CONSOLE) classify:run $(ARGS)

export: ## Export results: var/maps.json, var/sub_areas.json and var/report.html (ids, --area, --biome, --only-ok, --format=json|html)
	$(CONSOLE) export $(ARGS)

report: ## Only export the HTML gallery var/report.html (ids, --area, --biome…)
	$(CONSOLE) export --format=html $(ARGS)

sprites-classify: ## Find the type of sprites, then export var/sprites.json and var/sprites.html (ids like o345 g10-20, --set, --limit…)
	$(CONSOLE) sprites:classify $(ARGS)

sprites: ## Analyze sprites (var/sprite_profiles.json), render them to PNG, export var/sprites.json and generate var/sprites.html (selection, --min-ratio)
	$(CONSOLE) sprites:analyze $(ARGS)
	$(CONSOLE) sprites:images
	@# Analyze again: the slope flag needs the frames of the exported images (fast, ~5s)
	$(CONSOLE) sprites:analyze $(ARGS)
	$(CONSOLE) sprites:export

sprites-export: ## Export sprites profiles with their type: var/sprites.json and var/sprites.html (--format=json|html)
	$(CONSOLE) sprites:export $(ARGS)

pipeline: ## Render, classify and export the selection: make pipeline ARGS="--area=18"
	$(CONSOLE) maps:render $(ARGS)
	$(CONSOLE) classify:run $(ARGS)
	$(CONSOLE) export
