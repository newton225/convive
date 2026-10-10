# Campagne de tests du 2026-10-10

Rapport de la campagne QA (tests de bout en bout, securite, performance, concurrence, charge). Les outils sont
decrits dans [`qa/README.md`](../qa/README.md). Ce document ne reprend que ce qui est **etabli** : un resultat
qui n'a pas ete conserve est signale comme tel, jamais reconstitue.

## 1. Ce qui est couvert

| Domaine | Ou | Contenu |
| --- | --- | --- |
| Acces au back-office | `tests/Security/BackOfficeAccessSweepTest.php` | Balayage de toutes les routes : acces par role, 404 entre organisations, ecritures refusees avant validation |
| Surface publique | `tests/Security/PublicSurfaceFuzzTest.php` | Entrees hostiles sur les liens publics, jetons et formulaires |
| Isolation des donnees | `tests/Security/TenantDataIsolationTest.php` | Aucune route ne repond avec les donnees d'une autre organisation, meme en forcant l'acces |
| Jeton QR | `tests/Security/TicketTokenMutationTest.php` | Mutation du jeton signe (octets modifies, saut de ligne, etc.) |
| Budget de requetes | `tests/Performance/QueryBudgetTest.php` | Nombre de requetes SQL par page, independant du volume (pas de N+1), temps avec 2 000 inscriptions |
| Parcours de bout en bout | `e2e/` (Playwright) | `organiser-lifecycle`, `organiser-forms`, `auth`, `guest-edge`, `guest-journey`, `guest-registration`, `access`, `site`, `organiser` |
| Intrusion en boite noire | `qa/pentest.mjs` | En-tetes, CSRF, authentification, autorisation, enumeration, injections, depots hostiles, fichiers sensibles, limitation de debit |
| Concurrence reelle | `qa/race.mjs` | Plusieurs processus PHP se disputent la derniere place |
| Charge | `qa/load.mjs` | Debit et latences a 1, 5, 10, 25, 50 utilisateurs, 1 puis 4 processus PHP |

## 2. Resultats conserves

### Budget de requetes (`qa/results/queries.json`, 2026-10-10)

Le nombre de requetes SQL ne depend pas du nombre d'inscrits : passer de 6 a 66 inscrits ne l'augmente pas
(il baisse meme d'une unite ou deux sur la liste des evenements et le tableau de bord, par effet de cache).

| Page | 6 inscrits | 66 inscrits |
| --- | --- | --- |
| Liste des evenements | 61 | 56 |
| Tableau de bord | 69 | 67 |
| Base d'inscrits | 52 | 52 |
| File des preuves | 46 | 47 |
| Rapport de l'evenement | 41 | 41 |
| Plan de salle | 41 | 41 |
| Rapprochement | 40 | 40 |
| Page publique de l'evenement | 39 | 39 |
| Page du dossier de l'invite | 40 | 40 |

Les trois pages les plus gourmandes (liste des evenements, tableau de bord, base d'inscrits) tournent autour de
50 a 70 requetes : stable, mais a surveiller si la liste continue de grandir.

### Temps avec 2 000 inscriptions (`qa/results/timings.json`, 2026-10-10)

| Mesure | Temps | Plafond du test |
| --- | --- | --- |
| Base d'inscrits, page 1 | 0,31 s | 3 s |
| Base d'inscrits, recherche | 0,25 s | 3 s |
| Base d'inscrits, derniere page | 0,16 s | 3 s |
| Base d'inscrits, filtre a verifier | 0,20 s | 3 s |
| File des preuves | 0,38 s | 4 s |
| Rapport de l'evenement | 0,34 s | 4 s |
| Tableau de bord | 0,71 s | 3 s |
| Plan de salle | 0,35 s | 4 s |
| Export CSV | 3,66 s | 10 s |
| Export Excel | 4,33 s | 25 s |

Toutes les mesures sont sous leur plafond. Les pages paginees restent legeres (environ 220 Ko) grace a la
pagination serveur. L'export Excel reste le geste le plus lourd (22 Mo de memoire pour 2 000 lignes).

## 3. Resultats non conserves

Ces trois campagnes ont des outils dans le depot, mais **aucun resultat n'est enregistre** (`qa/results/` ne
contient ni `pentest.json`, ni `race.json`, ni `load.json`) : elles sont a rejouer pour qu'un chiffre existe.

- Intrusion : `node qa/pentest.mjs` (environ 6 min).
- Concurrence reelle : `node qa/race.mjs` (environ 2 min).
- Charge : `node qa/load.mjs` (environ 15 min).

Les suites PHP (`tests/Security`, `tests/Performance`) et Playwright n'ont pas non plus de rapport de passage
rejoue apres les derniers correctifs (voir le suivi des tests a lancer).

## 4. Defauts trouves et corriges pendant la campagne

Commits `7a7091e`, `2d16f38`, `0ec2ee9`, `63ca466` :

- La reclamation d'un invite resolvait mal l'inscription sur une route a domaine.
- Autorisation jouee apres la validation (renommer l'organisation, inviter) : un membre non autorise lisait un
  message de validation. Elle passe avant, dans `authorize()`.
- Requetes en lot dans la base d'inscrits et la file de preuves (N+1).
- Jeton QR accepte avec un saut de ligne final : desormais strict.
- File de preuves : cas d'une preuve sans inscription coherente.
- Remboursement : noms de champs lisibles dans les erreurs ; bouton d'ajout de tables reperable.
- Pages publiques sur sous-domaine : les scripts du build etaient reecrits en `/tenancy/assets` (404) par
  `asset_helper_tenancy` ; avertissements d'hydratation du lien de reprise, du compte a rebours et du style a nonce.

## 5. Pieges d'execution

- Deux series en parallele se suppriment leurs bases de test (`storage/framework/testing/tenant-databases`) :
  erreurs « no such table model_has_profiles ». Verifier qu'une seule serie tourne.
- `public/hot` perime : page blanche. Le supprimer avant un test de bout en bout, puis `npm run build`.
- Redis requis (cache par organisation) ; les attaques et la charge visent l'application e2e, jamais le serveur
  de developpement.
