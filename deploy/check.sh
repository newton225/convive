#!/usr/bin/env bash
# Vérifie l'application après une livraison : services, pages publiques, journaux.
# Usage : bash deploy/check.sh
# Lecture seule : ne modifie rien.

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
require_files

failed=0

step "Services"
docker compose ps

step "Pages publiques (200 attendu)"
for path in /up /login /confidentialite; do
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$PUBLIC_URL$path" || true)"
    printf '%s%s : %s\n' "$PUBLIC_URL" "$path" "$code"
    [ "$code" = "200" ] || failed=1
done

step "Dernières lignes des journaux (aucune erreur attendue)"
docker compose logs --tail=20 app queue scheduler

if [ "$failed" -ne 0 ]; then
    fail "au moins une page ne répond pas 200. Voir ci-dessus."
fi

printf '\nVérification terminée : pages en 200. Contrôler aussi la santé technique dans /console.\n'
