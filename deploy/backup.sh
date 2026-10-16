#!/usr/bin/env bash
# Sauvegarde de toutes les bases et des fichiers (convive:backup).
# Usage : bash deploy/backup.sh
# L'archive reste sur le serveur (volume convive-storage) : elle protège d'une erreur de
# livraison, pas de la perte du serveur.

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
require_files

step "Sauvegarde"
docker compose exec -T app php artisan convive:backup
printf '\nSauvegarde terminée.\n'
