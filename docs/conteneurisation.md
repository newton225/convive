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
docker compose -f docker-compose.prod.yml --env-file .env.production build app web
docker compose -f docker-compose.prod.yml --env-file .env.production run --rm migrate
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --no-build
```

`build app web` et non `build` seul : `app`, `queue`, `scheduler` et `migrate` partagent l'image `convive-app`, que
seul `app` construit. Construite par chacun, Docker les exporterait en parallele sous le meme nom (« image already
exists »).

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

Procédure suivie sur le serveur du pilote (VPS AlmaLinux, code dans `/opt/convive`). Les commandes se jouent
dans le dossier du dépôt, après avoir posé une fois par session :

```
export COMPOSE_FILE=docker-compose.prod.yml COMPOSE_ENV_FILES=.env.production
```

### 1. Avant de commencer

- Le code à livrer est sur `origin/main`. Si la livraison touche au schéma ou aux textes juridiques, le message
  de commit le dit.
- Noter le commit en service (`git rev-parse --short HEAD`) : c'est le point de retour.
- Prendre une sauvegarde, surtout si le schéma change (la sauvegarde planifiée passe à 2 h 30) :

```
docker compose exec -T app php artisan convive:backup
```

Cette archive reste sur le serveur : elle protège d'une erreur de livraison, pas de la perte du serveur.

### 2. Livrer

```
git pull
docker compose build app web 2>&1 | tail -6
docker compose run --rm migrate
docker compose up -d --no-build --force-recreate app queue scheduler web
docker compose ps
```

- `git pull` qui refuse (modifications locales) : ne pas forcer, comprendre d'abord.
- `build app web` et non `build` seul (voir plus haut). Attendu : `convive-app:latest` et `convive-web:latest`,
  sans `ERROR`.
- `migrate` enchaîne ses étapes avec `&&` : au premier échec, la suite ne part pas. S'arrêter là et lire
  l'erreur, ne pas relancer l'étape suivante à la main. Le site tourne encore sur l'ancien code pendant cette
  étape : une migration qui ne fait qu'ajouter des tables ou des colonnes est sans risque.
- `--force-recreate` est nécessaire : sans lui, la file et le planificateur gardent l'ancien code en mémoire.
  `docker compose ps` doit montrer cinq services `Up`, `web` et `redis` en `healthy`.

### 3. Vérifier

```
curl -s -o /dev/null -w "%{http_code}\n" https://app.devultraapp.com/up
curl -s -o /dev/null -w "%{http_code}\n" https://app.devultraapp.com/login
docker compose logs --tail=40 app queue scheduler
```

Attendu : 200 partout, aucune erreur dans les journaux. Dans la console, l'écran de santé technique doit être
entièrement au vert, avec le numéro de version et l'estampille de la livraison. Puis tester dans le navigateur
(Ctrl+F5) l'écran que la livraison a modifié.

### 4. Retour arrière

- **Migrations qui n'ont fait qu'ajouter** : revenir au commit noté, reconstruire et recréer.

```
git checkout <commit noté>
docker compose build app web 2>&1 | tail -6
docker compose up -d --no-build --force-recreate app queue scheduler web
```

Revenir ensuite sur `main` (`git checkout main`) avant la livraison suivante.

- **Migration qui a modifié des données ou une structure** : une migration ne s'annule pas à chaud, on restaure
  l'archive de `convive:backup` prise avant, application arrêtée (voir `CLAUDE.md`, « Sauvegardes »). Ne pas
  toucher à la base à la main.

### 5. Cas particuliers

| Situation | À faire en plus |
| --- | --- |
| Nouvelle variable dans `.env.production` | Éditer le fichier (droits 600), puis recréer les services (`up -d --no-build --force-recreate`). Jamais de secret dans une conversation ou un ticket. |
| Changement de `APP_VERSION` | Jouer `migrate` : `convive:release` l'estampille, puis recréer. |
| Seul le front change | Les mêmes étapes : `migrate` passe à vide, sans danger. |
| Mise à jour de Docker ou de PostgreSQL | À part, jamais dans la même livraison qu'une version de l'application. |

### 6. Avant d'ouvrir à d'autres organisations

À ne pas oublier, car la procédure ci-dessus suffit pour un pilote privé mais pas pour l'ouverture :

- copier `APP_KEY` hors du serveur (elle chiffre les clés de signature des billets, la 2FA et les sessions) ;
- sauvegardes : destination externe (`BACKUP_DISK`), `BACKUP_ARCHIVE_PASSWORD`, exercice de restauration sur
  ce serveur (le `pg_dump` de l'image est plus récent que PostgreSQL 16 : vérifier la relecture) ;
- suite de tests complète sur PostgreSQL et grille de `SECURITY.md` (concurrence sur la dernière place) ;
- un second Fondateur de la console, pour ne pas dépendre d'un seul compte à double authentification ;
- `convive:production-check` au vert (identité légale de l'éditeur, Turnstile réel, relecture juridique).
