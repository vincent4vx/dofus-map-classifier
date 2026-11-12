COMPOSE = DOCKER_UID=$(shell id -u) DOCKER_GID=$(shell id -g) docker compose
RUN = $(COMPOSE) run --rm php
CONSOLE = $(RUN) bin/console

# Extra arguments passed to the command, e.g. make render ARGS="--super-area=3"
ARGS ?=

.PHONY: help build shell install test console keys render classify submit status import export report objects pipeline

help: ## Display this help
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'
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

console: ## Run any console command: make console ARGS="list"
	$(CONSOLE) $(ARGS)

## Workflow

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

export: ## Export results to var/maps.json and var/sub_areas.json (--only-ok)
	$(CONSOLE) export $(ARGS)

report: ## Generate the HTML report var/report.html (ids, --area, --biome…)
	$(CONSOLE) report $(ARGS)

objects: ## Analyze sprites (var/objects.json), export them to SVG and generate var/objects.html (selection, --min-ratio)
	$(CONSOLE) objects:analyze $(ARGS)
	$(CONSOLE) objects:sprites
	$(CONSOLE) objects:report

pipeline: ## Render, classify synchronously, export and report the selection: make pipeline ARGS="--area=18"
	$(CONSOLE) maps:render $(ARGS)
	$(CONSOLE) classify:run $(ARGS)
	$(CONSOLE) export
	$(CONSOLE) report
