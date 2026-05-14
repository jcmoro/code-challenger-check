# --------------------------------------------------------------------
# CHECK24 Car Insurance Comparison — entry point for every developer task.
#
# All targets shell out to docker compose. The host needs only Docker
# and GNU Make. See `make help` for the menu, grouped by workflow.
# --------------------------------------------------------------------

SHELL := /bin/bash
.SHELLFLAGS := -eu -o pipefail -c
.DEFAULT_GOAL := help

# Compose & service handles
COMPOSE       := docker compose
BACKEND       := $(COMPOSE) exec backend
BACKEND_RUN   := $(COMPOSE) run --rm --no-deps backend
FRONTEND      := $(COMPOSE) exec frontend
FRONTEND_RUN  := $(COMPOSE) run --rm --no-deps frontend

# Bootstrap helpers (no project files yet → use one-off containers)
COMPOSER_BOOTSTRAP := docker run --rm -v $(CURDIR):/app -w /app composer:2
NODE_BOOTSTRAP     := docker run --rm -v $(CURDIR):/app -w /app node:20-alpine

# Colours
C_BLUE  := \033[36m
C_BOLD  := \033[1m
C_DIM   := \033[2m
C_RESET := \033[0m


# ====================================================================
# Help
# ====================================================================

## help: Print this menu, grouped by workflow
help:
	@printf "$(C_BOLD)CHECK24 Car Insurance Comparison — Make targets$(C_RESET)\n"
	@printf "$(C_DIM)All commands run inside Docker. Prerequisites: Docker + GNU Make.$(C_RESET)\n"
	@awk 'BEGIN {FS = ":.*?## "} \
		/^##@ / { printf "\n$(C_BOLD)%s$(C_RESET)\n", substr($$0, 5); next } \
		/^## /  { sub(/^## /, "", $$0); split($$0, a, ":"); printf "  $(C_BLUE)%-22s$(C_RESET) %s\n", a[1], a[2] }' \
		$(MAKEFILE_LIST)
	@printf "\n"


# ====================================================================
##@ Setup (one-off, plus image build + dependency install)
# ====================================================================

## bootstrap: One-time — create Symfony & Vue projects (idempotent)
bootstrap: bootstrap-backend bootstrap-frontend
	@echo ">> Bootstrap complete. Next: make build && make install && make up-d"

## bootstrap-backend: Create the Symfony skeleton in ./backend
bootstrap-backend:
	@if [ -d backend ] && [ -f backend/composer.json ]; then \
		echo ">> backend/ already bootstrapped, skipping"; \
	else \
		echo ">> Creating Symfony skeleton in ./backend"; \
		$(COMPOSER_BOOTSTRAP) create-project symfony/skeleton:"^7.3" backend --no-interaction; \
	fi

## bootstrap-frontend: Create the Vue skeleton in ./frontend
bootstrap-frontend:
	@if [ -d frontend ] && [ -f frontend/package.json ]; then \
		echo ">> frontend/ already bootstrapped, skipping"; \
	else \
		echo ">> Creating Vue + Vite + TS skeleton in ./frontend"; \
		$(NODE_BOOTSTRAP) sh -c "npm create vite@latest frontend -- --template vue-ts --yes"; \
	fi

## build: Build all Docker images
build:
	$(COMPOSE) build

## install: Install backend + frontend dependencies
install: install-backend install-frontend

## install-backend: composer install (inside the backend image)
install-backend:
	$(BACKEND_RUN) composer install --no-interaction --prefer-dist

## install-frontend: npm ci (inside the frontend image)
install-frontend:
	$(FRONTEND_RUN) sh -c "if [ -f package-lock.json ]; then npm ci; else npm install; fi"


# ====================================================================
##@ Run (daily lifecycle)
# ====================================================================

## up: Start the full stack in the foreground
up:
	$(COMPOSE) up

## up-d: Start the full stack detached
up-d:
	$(COMPOSE) up -d

## down: Stop the stack (keeps named volumes)
down:
	$(COMPOSE) down

## logs: Tail logs from all services
logs:
	$(COMPOSE) logs -f --tail=200

## ps: Show running services
ps:
	$(COMPOSE) ps


# ====================================================================
##@ Containers (drop into a shell)
# ====================================================================

## shell-backend: Open a bash shell inside the backend container
shell-backend:
	$(BACKEND) bash

## shell-frontend: Open a sh shell inside the frontend container
shell-frontend:
	$(FRONTEND) sh


# ====================================================================
##@ Test (PHPUnit + Vitest)
# ====================================================================

## test: Run all tests (backend + frontend)
test: test-backend test-frontend

## test-backend: Run PHPUnit
test-backend:
	$(BACKEND_RUN) sh -c "if [ -x bin/phpunit ]; then bin/phpunit; elif [ -x vendor/bin/phpunit ]; then vendor/bin/phpunit; else echo 'PHPUnit not installed yet — skipping'; fi"

## test-frontend: Run Vitest
test-frontend:
	$(FRONTEND_RUN) sh -c "if [ -f package.json ] && npm run | grep -q '^  test'; then npm run test -- --run; else echo 'Vitest not wired up yet — skipping'; fi"


# ====================================================================
##@ Quality (lint + static analysis, all in check mode)
# ====================================================================

## lint: Run every static check (stan + cs + eslint + prettier + typecheck)
lint: stan cs eslint prettier typecheck

## stan: PHPStan analysis
stan:
	$(BACKEND_RUN) sh -c "if [ -x vendor/bin/phpstan ]; then vendor/bin/phpstan analyse --no-progress; else echo 'PHPStan not installed yet — skipping'; fi"

## cs: PHP-CS-Fixer (dry-run, fails on drift)
cs:
	$(BACKEND_RUN) sh -c "if [ -x vendor/bin/php-cs-fixer ]; then vendor/bin/php-cs-fixer fix --dry-run --diff; else echo 'PHP-CS-Fixer not installed yet — skipping'; fi"

## eslint: ESLint (fails on any warning)
eslint:
	$(FRONTEND_RUN) sh -c "if [ -f package.json ] && npm run | grep -q '^  lint'; then npm run lint; else echo 'ESLint not wired up yet — skipping'; fi"

## prettier: Prettier --check
prettier:
	$(FRONTEND_RUN) sh -c "if [ -f package.json ] && npm run | grep -q '^  format:check'; then npm run format:check; else echo 'Prettier not wired up yet — skipping'; fi"

## typecheck: vue-tsc --noEmit (strict TS check)
typecheck:
	$(FRONTEND_RUN) sh -c "if [ -f package.json ] && npm run | grep -q '^  typecheck'; then npm run typecheck; else echo 'tsc not wired up yet — skipping'; fi"


# ====================================================================
##@ Fix (auto-fixers in write mode)
# ====================================================================

## fix: Apply every auto-fixer (backend + frontend)
fix: fix-backend fix-frontend

## fix-backend: PHP-CS-Fixer in write mode
fix-backend:
	$(BACKEND_RUN) sh -c "if [ -x vendor/bin/php-cs-fixer ]; then vendor/bin/php-cs-fixer fix; else echo 'PHP-CS-Fixer not installed yet'; fi"

## fix-frontend: ESLint --fix + Prettier --write
fix-frontend:
	$(FRONTEND_RUN) sh -c "if [ -f package.json ] && npm run | grep -q '^  lint:fix'; then npm run lint:fix && npm run format; else echo 'Frontend fixers not wired up yet'; fi"


# ====================================================================
##@ Reset (destructive — prompts before acting)
# ====================================================================

## clean: Remove containers, volumes, and build artefacts
clean:
	@read -rp "This will remove containers, volumes and build artefacts. Continue? [y/N] " ans; \
	if [ "$$ans" = "y" ] || [ "$$ans" = "Y" ]; then \
		$(COMPOSE) down -v --remove-orphans; \
		rm -rf backend/var backend/vendor frontend/node_modules frontend/dist; \
		echo ">> Cleaned."; \
	else \
		echo ">> Aborted."; \
	fi


.PHONY: help \
        bootstrap bootstrap-backend bootstrap-frontend \
        build install install-backend install-frontend \
        up up-d down logs ps \
        shell-backend shell-frontend \
        test test-backend test-frontend \
        lint stan cs eslint prettier typecheck \
        fix fix-backend fix-frontend \
        clean
