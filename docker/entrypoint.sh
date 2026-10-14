#!/bin/sh
# Point d'entree commun a tous les roles de l'application (web, file, planificateur, migrations).
set -e

cd /var/www/html

# Les caches se construisent avec l'environnement du conteneur, pas celui de la construction de l'image.
# Une erreur ici (cle d'application absente, configuration invalide) arrete le conteneur : mieux vaut
# un conteneur qui ne demarre pas qu'une application qui tourne mal configuree.
php artisan config:cache
php artisan event:cache
php artisan view:cache

exec "$@"
