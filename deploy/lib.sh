#!/usr/bin/env bash
# Fonctions communes aux scripts de livraison. Se charge avec `source`, ne s'exécute pas seul.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

export COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
export COMPOSE_ENV_FILES="${COMPOSE_ENV_FILES:-.env.production}"

PUBLIC_URL="${PUBLIC_URL:-https://app.devultraapp.com}"
PREVIOUS_FILE="${PREVIOUS_FILE:-$HOME/.convive-previous-commit}"

step() { printf '\n== %s\n' "$1"; }
fail() { printf 'ERREUR : %s\n' "$1" >&2; exit 1; }

require_files() {
    [ -f "$COMPOSE_FILE" ] || fail "$COMPOSE_FILE introuvable : lancer depuis le dépôt de l'application."
    [ -f ".env.production" ] || fail ".env.production introuvable."
}

confirm() {
    local answer
    read -r -p "$1 [o/N] " answer
    [ "$answer" = "o" ] || [ "$answer" = "O" ] || fail "Abandon, rien n'a été modifié par cette étape."
}
