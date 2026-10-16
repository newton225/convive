# Scripts de mise en production

Automatisent la procédure de `docs/conteneurisation.md` (section « Livrer une nouvelle version »). À lancer
**sur le serveur**, depuis le dépôt (`/opt/convive`). Aucun secret ici : les scripts lisent `.env.production`
sans l'afficher.

| Script | Rôle |
| --- | --- |
| `deploy.sh` | Livre `main` : contrôle, sauvegarde, récupération, construction, migrations, recréation, vérification |
| `check.sh` | Vérifie l'état après livraison (lecture seule) |
| `backup.sh` | Sauvegarde les bases et les fichiers (`convive:backup`) |
| `rollback.sh` | Revient au commit noté avant la dernière livraison |
| `lib.sh` | Fonctions communes, ne se lance pas seul |

## Utilisation

```
cd /opt/convive
bash deploy/deploy.sh
```

Le script montre ce qui va être livré et demande confirmation (`o`). Il s'arrête au premier échec : ne pas
relancer à la main l'étape suivante, lire l'erreur.

- `bash deploy/deploy.sh --sans-sauvegarde` : saute la sauvegarde (à éviter si le schéma change).
- `bash deploy/check.sh` : à tout moment, pour contrôler les services, les pages (200 attendu) et les journaux.
- `bash deploy/rollback.sh` : retour au commit noté dans `~/.convive-previous-commit`.

Réglages par variables d'environnement : `PUBLIC_URL` (adresse contrôlée, `https://app.devultraapp.com` par
défaut) et `PREVIOUS_FILE` (fichier du point de retour).

## Les commandes, une par une

Ce que fait `deploy.sh`, à jouer à la main si un script pose problème. Une fois par session :

```
cd /opt/convive
export COMPOSE_FILE=docker-compose.prod.yml COMPOSE_ENV_FILES=.env.production
```

Livrer :

```
git rev-parse --short HEAD                                   # noter le commit en service
docker compose exec -T app php artisan convive:backup        # sauvegarde
git pull                                                     # récupérer le code
docker compose build app web 2>&1 | tail -6                  # construire les images
docker compose run --rm migrate                              # migrations
docker compose up -d --no-build --force-recreate app queue scheduler web
docker compose ps                                            # cinq services Up
```

Vérifier :

```
curl -s -o /dev/null -w "%{http_code}\n" https://app.devultraapp.com/up
curl -s -o /dev/null -w "%{http_code}\n" https://app.devultraapp.com/login
curl -s -o /dev/null -w "%{http_code}\n" https://app.devultraapp.com/confidentialite
docker compose logs --tail=40 app queue scheduler
```

Revenir en arrière (migrations qui n'ont fait qu'ajouter) :

```
git checkout <commit noté>
docker compose build app web 2>&1 | tail -6
docker compose up -d --no-build --force-recreate app queue scheduler web
git checkout main                                            # avant la livraison suivante
```

## Ce que les scripts ne font pas

- **Ils n'annulent pas les migrations.** `rollback.sh` ne vaut que si elles n'ont fait qu'ajouter des tables ou
  des colonnes. Sinon : restaurer l'archive de `convive:backup`, application arrêtée (`CLAUDE.md`, « Sauvegardes »).
- **Ils ne changent pas le numéro de version.** `APP_VERSION` se choisit à la main (`config/convive.php`).
- Ils ne touchent ni à PostgreSQL, ni au certificat, ni à nginx de l'hôte.
- Ils ne remplacent pas le contrôle dans la console (santé technique au vert).

Si `git pull` refuse à cause de modifications locales, `deploy.sh` s'arrête : ne pas forcer.
