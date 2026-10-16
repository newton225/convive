#!/usr/bin/env bash
# Livre la dernière version de `main` : sauvegarde, récupération, construction, migrations,
# recréation des services, vérification.
# Usage : bash deploy/deploy.sh [--sans-sauvegarde]
#
# S'arrête au premier échec. Le commit en service est noté dans $PREVIOUS_FILE pour deploy/rollback.sh.

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
require_files

skip_backup=0
for arg in "$@"; do
    case "$arg" in
        --sans-sauvegarde) skip_backup=1 ;;
        *) fail "option inconnue : $arg" ;;
    esac
done

[ -z "$(git status --porcelain --untracked-files=no)" ] \
    || fail "le dépôt a des modifications locales. Ne pas forcer : les comprendre d'abord."

current="$(git rev-parse HEAD)"
step "Commit en service : $(git rev-parse --short HEAD)"
git fetch origin main
incoming="$(git rev-parse origin/main)"

if [ "$current" = "$incoming" ]; then
    printf 'Déjà à jour (%s). ' "$(git rev-parse --short HEAD)"
    confirm "Reconstruire et relivrer quand même ?"
else
    printf 'À livrer :\n'
    git log --oneline "$current..$incoming" | head -20
    confirm "Livrer ces modifications ?"
fi

if [ "$skip_backup" -eq 0 ]; then
    step "Sauvegarde avant livraison"
    docker compose exec -T app php artisan convive:backup
else
    printf '\nSauvegarde ignorée à votre demande.\n'
fi

printf '%s\n' "$current" > "$PREVIOUS_FILE"
printf 'Point de retour noté : %s\n' "$(git rev-parse --short "$current")"

step "Récupération du code"
git checkout main
git pull --ff-only origin main

step "Construction des images (app et web seulement)"
docker compose build app web 2>&1 | tail -6

step "Migrations (le site tourne encore sur l'ancien code)"
docker compose run --rm migrate

step "Bascule sur le nouveau code"
docker compose up -d --no-build --force-recreate app queue scheduler web

step "Attente du démarrage"
sleep 10

bash "$ROOT/deploy/check.sh"

printf '\nLivraison terminée : %s. Retour arrière : bash deploy/rollback.sh\n' "$(git rev-parse --short HEAD)"
