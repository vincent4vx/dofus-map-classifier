COMPOSE = DOCKER_UID=$(shell id -u) DOCKER_GID=$(shell id -g) docker compose
RUN = $(COMPOSE) run --rm php

.PHONY: build shell install test console

build:
	$(COMPOSE) build

shell: build
	$(RUN) bash

install: build
	$(RUN) composer install

test:
	$(RUN) vendor/bin/phpunit $(ARGS)

# Usage: make console ARGS="maps:render 10000"
console:
	$(RUN) bin/console $(ARGS)
