#!/usr/bin/env bash
# Revient au commit noté par deploy/deploy.sh.
# Usage : bash deploy/rollback.sh
#
# Ne défait PAS les migrations. Valable seulement si elles n'ont fait qu'ajouter des tables ou des
# colonnes. Si une migration a modifié des données ou une structure, ne pas utiliser ce script :
# restaurer l'archive de convive:backup, application arrêtée (CLAUDE.md, « Sauvegardes »).

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
require_files

[ -f "$PREVIOUS_FILE" ] || fail "aucun point de retour noté ($PREVIOUS_FILE). Rien à restaurer."
target="$(cat "$PREVIOUS_FILE")"
git cat-file -e "$target^{commit}" 2>/dev/null || fail "le commit $target est introuvable dans ce dépôt."

step "Retour arrière"
printf 'Commit en service : %s\n' "$(git rev-parse --short HEAD)"
printf 'Retour vers       : %s\n' "$(git rev-parse --short "$target")"
printf '\nLes migrations déjà jouées ne sont pas annulées.\n'
confirm "Revenir à ce commit ?"

git checkout "$target"

step "Construction des images"
docker compose build app web 2>&1 | tail -6

step "Bascule"
docker compose up -d --no-build --force-recreate app queue scheduler web
sleep 10

bash "$ROOT/deploy/check.sh"

git rev-parse HEAD > "$DELIVERED_FILE"

printf '\nRetour effectué sur %s (tête détachée). Avant la prochaine livraison : deploy.sh repasse sur main.\n' \
    "$(git rev-parse --short HEAD)"
