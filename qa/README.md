# Outils de la campagne QA

Ces scripts rejouent la campagne de tests decrite dans [`docs/README-tests.md`](../docs/README-tests.md). Ils
attaquent, chargent et mesurent **l'application de test** (celle des tests de bout en bout : base et fichiers
sous `storage/e2e`, ports 8020 a 8033), jamais le serveur ni les donnees de developpement.

Prerequis : `npm run build` (ou `npm run dev`) une fois, Redis lance (le cache par organisation l'exige), et
`php` dans le PATH.

| Script | Ce qu'il fait | Duree |
| --- | --- | --- |
| `node qa/pentest.mjs` | Tests d'intrusion en boite noire : en-tetes, CSRF, authentification, autorisation, enumeration, injections, depots de fichiers hostiles, fichiers sensibles, protocole, divulgation, limitation de debit. Ecrit `qa/results/pentest.json`. | ~6 min |
| `node qa/race.mjs` | Concurrence reelle : des dizaines de processus PHP se disputent la derniere place sur la meme base. Ecrit `qa/results/race.json`. | ~2 min |
| `node qa/load.mjs [inscriptions] [secondes]` | Performance et charge : pose un volume (3 000 inscriptions par defaut), puis mesure debit et latences a 1, 5, 10, 25, 50 utilisateurs, avec 1 puis 4 processus PHP. Ecrit `qa/results/load.json`. | ~15 min |
| `php artisan test tests/Security` | Balayage de toutes les routes (acces, 404 entre organisations, ecritures refusees avant validation), fuzz de la surface publique, isolation des donnees, mutation du jeton QR. | ~3 min |
| `php artisan test tests/Performance` | Budget de requetes SQL (pas de N+1) et temps avec 2 000 inscriptions. Ecrit `qa/results/queries.json` et `qa/results/timings.json`. | ~2 min |
| `npm run test:e2e` | Parcours de bout en bout (Playwright), dont `organiser-lifecycle`, `organiser-forms`, `auth`, `guest-edge`. | ~15 min |

Fichiers :

- `http.mjs` : client HTTP minimal (cookies, Host libre, sans redirection) et calcul de percentiles ;
- `e2e-server.mjs` : prepare l'application de test et lance un ou plusieurs serveurs PHP ;
- `facts.php`, `seed-volume.php`, `race-*.php` : lecture des identifiants, volume de donnees, scenario de concurrence.

Regle du projet : ces campagnes se lancent le soir (voir `CLAUDE.md` et la memoire du proprietaire), jamais en
parallele de la suite PHP : deux series en meme temps se suppriment leurs bases de test.
