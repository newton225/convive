# Convive en conteneurs

Rédigé le 2026-10-13. Fichiers : `Dockerfile`, `docker-compose.prod.yml`, `docker/` (nginx, PHP, point
d'entrée), `.dockerignore`, `.env.production.docker.example`. **Aucun secret ici.**

Décision du propriétaire du projet (2026-10-13) : en production, **la base est PostgreSQL installé sur le
serveur physique**, hors des conteneurs.

## Ce qui tourne

| Service | Rôle |
| --- | --- |
| `web` | nginx : fichiers statiques de `public`, le reste va à PHP-FPM. Publie `127.0.0.1:8080` |
| `app` | PHP-FPM : les pages |
| `queue` | `queue:work` : cartes, rappels, alertes |
| `scheduler` | `schedule:work` : expirations, rappels, alertes de fin, sauvegardes, contrôles |
| `redis` | cache à étiquettes et files, mot de passe, jamais exposé hors du réseau Docker |
| `migrate` | une fois par livraison : `migrate`, `tenants:migrate`, `tenants:sync-permissions`, `convive:release`, `queue:restart` (profil `deploy`) |

Les trois rôles de l'application (`app`, `queue`, `scheduler`) utilisent **la même image** et le **même
volume** `convive-storage` (marque, preuves de paiement, sauvegardes, journaux).

## Mise en route sur le serveur

```
cp .env.production.example .env.production      # puis ajouter le contenu de .env.production.docker.example
# renseigner APP_KEY (php artisan key:generate --show), REDIS_PASSWORD, les secrets
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
docker compose -f docker-compose.prod.yml --env-file .env.production run --rm migrate
```

Le serveur de tête de l'hôte (nginx, Caddy ou Traefik) reçoit `*.domaine` en HTTPS avec le certificat
générique et transmet à `127.0.0.1:8080`. Ses adresses vont dans `TRUSTED_PROXIES`. Il doit transmettre
`Host`, `X-Forwarded-For` et `X-Forwarded-Proto`.

## Joindre PostgreSQL sur l'hôte

Les conteneurs voient l'hôte sous `host.docker.internal` (déclaré par `extra_hosts` dans le compose). Sur
l'hôte, PostgreSQL doit :

1. écouter sur l'interface du pont Docker (`listen_addresses` dans `postgresql.conf`), pas seulement sur
   `127.0.0.1` ;
2. accepter le sous-réseau Docker dans `pg_hba.conf` (par exemple `host convive convive 172.16.0.0/12 scram-sha-256`) ;
3. être protégé par le pare-feu : le port 5432 n'est **pas** ouvert sur Internet ;
4. avoir un rôle applicatif avec `CREATEDB` : `stancl/tenancy` crée **une base par organisation**.

Le client `pg_dump` de l'image (`postgresql-client`) doit être au moins de la version du serveur. L'extension `unaccent` vient de `postgresql-contrib` (dans le paquet principal sur Debian et Ubuntu).

## L'application et PostgreSQL

Le chantier est fait pour l'essentiel (2026-10-14, vérifié sur PostgreSQL 18 en local) :

| Partie | État |
| --- | --- |
| Bases des organisations | Une base par organisation (`tenant_<ULID>`), créée avant la transaction, migrée à la création |
| Bases orphelines et effacement | Lus dans `pg_database`, suppression par `DROP DATABASE` |
| Recherche sans accents | Extension `unaccent`, activée par migration (centrale et par organisation) |
| Sauvegardes | `pg_dump` base par base, restauration par `pg_restore` (voir `CLAUDE.md`, « Sauvegardes ») |
| Suite de tests complète | **Pas encore rejouée sur PostgreSQL** |
| Verrous (`lockForUpdate()`), concurrence sur la dernière place | **Grille de `SECURITY.md` à rejouer** : actif sur PostgreSQL, inopérant sur SQLite |

Tant que les deux dernières lignes ne sont pas faites, **ne pas ouvrir au public** : un déploiement pilote, privé,
avec une seule organisation, reste possible. Réglage dans le `.env` de production : `DB_CONNECTION=central`,
`DB_CENTRAL_DRIVER=pgsql`, `DB_CENTRAL_DATABASE`, `DB_HOST=host.docker.internal`, `DB_USERNAME`, `DB_PASSWORD`
(`DB_DATABASE` n'est pas lu). `PG_DUMP_BINARY` reste `pg_dump` dans l'image.

## Livrer une nouvelle version

```
git pull
docker compose -f docker-compose.prod.yml --env-file .env.production build
docker compose -f docker-compose.prod.yml --env-file .env.production run --rm migrate
docker compose -f docker-compose.prod.yml --env-file .env.production up -d
```

Retour arrière : garder l'image précédente (`APP_IMAGE_TAG`) et l'archive de `convive:backup` prise avant la
migration. Une migration ne s'annule pas à chaud : on restaure.
