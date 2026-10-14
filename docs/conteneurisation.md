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

Le client `pg_dump` de l'image (`postgresql-client`) doit être au moins de la version du serveur.

## Avertissement : l'application n'est pas encore prête pour PostgreSQL

Tout ce qui précède prépare l'infrastructure. **Le code n'a jamais tourné sur PostgreSQL**, et plusieurs
parties sont écrites pour SQLite. Repérées dans le code au 2026-10-13 :

| Partie | Ce qui est propre à SQLite |
| --- | --- |
| Bases des organisations | Un fichier par organisation : `config/tenancy.php` (`suffix => '.sqlite'`), `ScopedSqliteDatabaseManager`, `TenantDatabaseName` (`tenant_<ULID>.sqlite`) |
| Création d'organisation | `CreateTenant` efface le fichier laissé par une tentative échouée |
| Bases orphelines | `TenantDatabaseFiles`, `OrphanTenantDatabasesCheck`, `DeleteOrphanDatabase`, écran de santé : ils listent des fichiers |
| Sauvegardes | `DatabaseSnapshots` copie par `VACUUM INTO` et **refuse tout autre moteur** ; `convive:backup` est à reprendre avec `pg_dump` |
| Recherche sans accents | `unaccent` est fournie à SQLite par `AppServiceProvider` ; PostgreSQL demande `CREATE EXTENSION unaccent` (une migration) |
| Verrous | `lockForUpdate()` est inopérant en SQLite et actif en PostgreSQL : à rejouer avec de vrais tests de concurrence |
| Tests | La suite tourne en SQLite en mémoire ; il faut une exécution sur PostgreSQL |

Chantier proposé, **à ne lancer que sur votre accord explicite** (`CLAUDE.md`, « Base de données » : ne rien
changer sans demande explicite) :

1. un environnement PostgreSQL de développement (un conteneur, comme Redis aujourd'hui) ;
2. le gestionnaire de bases par organisation en PostgreSQL (`PostgreSQLDatabaseManager`, déjà référencé dans
   `config/tenancy.php`) et le nommage associé ;
3. orphelins et suppression d'une base d'organisation ;
4. sauvegardes avec `pg_dump` et exercice de restauration ;
5. extension `unaccent` ;
6. suite complète et parcours Playwright sur PostgreSQL ;
7. grille de `SECURITY.md` rejouée, en particulier la concurrence sur la dernière place.

Tant que ce chantier n'est pas fait, **ne pas déployer ces conteneurs avec `DB_CONNECTION=pgsql`**. Pour un
premier déploiement sur SQLite (un seul serveur, volume dédié aux fichiers de bases), il faudrait ajouter le
dossier `database` en volume : dites-le-moi et je prépare cette variante.

## Livrer une nouvelle version

```
git pull
docker compose -f docker-compose.prod.yml --env-file .env.production build
docker compose -f docker-compose.prod.yml --env-file .env.production run --rm migrate
docker compose -f docker-compose.prod.yml --env-file .env.production up -d
```

Retour arrière : garder l'image précédente (`APP_IMAGE_TAG`) et l'archive de `convive:backup` prise avant la
migration. Une migration ne s'annule pas à chaud : on restaure.
