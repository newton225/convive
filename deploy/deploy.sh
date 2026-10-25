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
git fetch origin main
incoming="$(git rev-parse origin/main)"

# Ce que les conteneurs servent vraiment : le dernier commit livré par ce script. Le code du dépôt
# (HEAD) peut déjà être plus récent si quelqu'un a fait un `git pull` seul : il n'a alors rien livré.
delivered="$(cat "$DELIVERED_FILE" 2>/dev/null || true)"
if [ -n "$delivered" ] && ! git cat-file -e "$delivered^{commit}" 2>/dev/null; then
    delivered=""
fi

step "Code du dépôt : $(git rev-parse --short HEAD)"
if [ -z "$delivered" ]; then
    printf 'Aucune livraison enregistrée par ce script : les conteneurs peuvent servir une version plus ancienne que le code.\n'
    if [ "$current" != "$incoming" ]; then
        printf 'À récupérer :\n'
        git log --oneline "$current..$incoming" | head -20
    fi
    confirm "Livrer ?"
elif [ "$delivered" = "$incoming" ]; then
    printf 'Déjà livré (%s) : les conteneurs servent le dernier code. ' "$(git rev-parse --short "$delivered")"
    confirm "Reconstruire et relivrer quand même ?"
else
    printf 'Les conteneurs servent %s. À livrer :\n' "$(git rev-parse --short "$delivered")"
    git log --oneline "$delivered..$incoming" | head -20
    confirm "Livrer ces modifications ?"
fi

if [ "$skip_backup" -eq 0 ]; then
    step "Sauvegarde avant livraison"
    docker compose exec -T app php artisan convive:backup
else
    printf '\nSauvegarde ignorée à votre demande.\n'
fi

# Le point de retour est ce que les conteneurs servaient, pas le code du dépôt s'il avait déjà avancé.
previous="${delivered:-$current}"
printf '%s\n' "$previous" > "$PREVIOUS_FILE"
printf 'Point de retour noté : %s\n' "$(git rev-parse --short "$previous")"

step "Récupération du code"
git checkout main
git pull --ff-only origin main

step "Construction des images (app et web seulement)"
# `--pull` : sans lui, Docker garde l'image PHP deja presente sur le serveur et ne recoit jamais les
# correctifs de securite de PHP (php2shell, CVE-2026-109618 et CVE-2026-109619, corriges en 8.3.36).
docker compose build --pull app web 2>&1 | tail -6

step "Versions embarquees (PHP, Laravel, base de donnees)"
# Le point d'entree de l'image joue d'abord `artisan config:cache` et ses lignes passent avant celles
# des versions : `head -1` lisait une ligne vide. On filtre donc les lignes utiles. `db:show` ouvre la
# base centrale (la premiere ligne donne le moteur et sa version) ; sans acces, le reste s'affiche quand meme.
docker compose run --rm --no-deps -T app sh -c 'php -v; php artisan --version --no-ansi; php artisan db:show --no-ansi' 2>&1 \
    | grep -E '^PHP |^Laravel Framework|PostgreSQL|MySQL|MariaDB|SQLite|SQLSTATE' | head -6 || true

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

git rev-parse HEAD > "$DELIVERED_FILE"

printf '\nLivraison terminée : %s. Retour arrière : bash deploy/rollback.sh\n' "$(git rev-parse --short HEAD)"
