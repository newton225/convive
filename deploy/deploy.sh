#!/usr/bin/env bash
# Livre la dernière version de `main` : sauvegarde, récupération, construction, migrations,
# recréation des services, vérification.
# Usage : bash deploy/deploy.sh [--sans-sauvegarde] [--version 0.2.0]
#
# Le numéro de version est demandé à chaque livraison (Entrée pour garder l'actuel) et écrit dans
# APP_VERSION de .env.production. --version le donne d'avance et évite la question.
#
# S'arrête au premier échec. Le commit en service est noté dans $PREVIOUS_FILE pour deploy/rollback.sh.

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
require_files

skip_backup=0
new_version=""
while [ "$#" -gt 0 ]; do
    case "$1" in
        --sans-sauvegarde) skip_backup=1 ;;
        --version)
            [ "$#" -ge 2 ] || fail "--version demande un numéro (par exemple 0.2.0)."
            new_version="$2"
            shift
            ;;
        *) fail "option inconnue : $1" ;;
    esac
    shift
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

step "Numéro de version"
# Lit la seule ligne APP_VERSION : le reste du fichier (secrets) n'est jamais affiché.
current_version="$(grep -E '^APP_VERSION=' .env.production | tail -n1 | cut -d= -f2- | tr -d '"' || true)"
current_version="${current_version:-0.1.0}"
if [ -n "$new_version" ]; then
    version="$new_version"
else
    read -r -p "Version en service : $current_version. Nouvelle version (Entrée pour garder) : " version
    version="${version:-$current_version}"
fi
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail "version invalide : « $version » (forme attendue : 0.2.0)."
if [ "$version" != "$current_version" ]; then
    if grep -qE '^APP_VERSION=' .env.production; then
        sed -i "s/^APP_VERSION=.*/APP_VERSION=$version/" .env.production
    else
        printf 'APP_VERSION=%s\n' "$version" >> .env.production
    fi
    printf 'APP_VERSION passée de %s à %s.\n' "$current_version" "$version"
else
    printf 'Version inchangée : %s.\n' "$version"
fi

step "Migrations (le site tourne encore sur l'ancien code)"
docker compose run --rm migrate

step "Bascule sur le nouveau code"
docker compose up -d --no-build --force-recreate app queue scheduler web

step "Attente du démarrage"
sleep 10

bash "$ROOT/deploy/check.sh"

printf '\nLivraison terminée : %s. Retour arrière : bash deploy/rollback.sh\n' "$(git rev-parse --short HEAD)"
