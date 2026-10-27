# 04. Sécurité

Les contrôles à tenir sur tout produit exposé à Internet, organisés par thème. Chaque règle porte un
identifiant, une raison et, quand c'est utile, la façon de la contrôler. La grille de test est dans
`10-checklists.md`.

## 1. Méthode

- **SEC-01.** **Modéliser les menaces avant de construire.** Chaque risque s'écrit au même format : **l'attaque**
  (ce que fait l'attaquant), **l'impact** (ce que ça coûte), **le contrôle obligatoire**. Un risque sans contrôle
  est une dette, pas une analyse.
- **SEC-02.** **Évaluer la gravité dans le contexte du produit** : argent détourné, ressource vendue deux fois,
  données d'un client lues par un autre, exécution de code sur le serveur. La gravité d'un défaut dépend de ce
  qu'il permet ici.
- **SEC-03.** La modélisation devient une **grille de test** rejouée avant chaque ouverture à de nouveaux
  clients et à chaque évolution qui touche à l'argent, aux accès ou aux données d'un client.
- **SEC-04.** Un contrôle de sécurité **ne s'affaiblit jamais** pour faciliter un ajout. Si un contrôle gêne, on
  le dit et le responsable tranche.
- **SEC-05.** Un point de la grille **non vérifié est supposé faux**.
- **SEC-06.** Défense **en profondeur** : une garantie ne repose jamais sur un seul mécanisme (verrou **et**
  contrainte d'unicité ; filtre **et** autorisation ; limiteur **et** vérification).

## 2. Authentification et sessions

- **SEC-10.** Mots de passe **hachés** avec un algorithme adaptatif ; règles de robustesse affichées au moment de
  la saisie ; jamais renvoyés, journalisés ni envoyés par courriel.
- **SEC-11.** **Double authentification** (TOTP) proposée à tous et **exigée** pour les profils qui touchent à
  l'argent, aux comptes ou aux accès. Le profil le plus privilégié l'exige toujours, drapeau ou pas : sinon son
  porteur lèverait l'exigence sur lui-même.
- **SEC-12.** **Un code TOTP ne se rejoue pas** dans sa fenêtre : mémoriser le dernier pas consommé.
- **SEC-13.** **Codes de secours** : stockés **hachés**, à usage unique, dénombrés, affichés **une seule fois** à
  la génération ; un code utilisé est retiré, pas remplacé. Vérifier que la comparaison du framework n'accepte
  pas l'empreinte elle-même comme code.
- **SEC-14.** **Verrouillage progressif** après échecs répétés sur la connexion et la 2FA, journalisé ; messages
  qui ne révèlent pas si le compte existe.
- **SEC-15.** **Re-authentification** (mot de passe, puis second facteur rejoué juste avant) pour les opérations
  sensibles : comptes de paiement, rôles, abonnement, suppression d'un membre, identité légale.
- **SEC-16.** **Régénérer la session** à la connexion, à l'élévation de privilège et au changement d'espace.
- **SEC-17.** **Sessions** : cookie `HttpOnly`, `Secure`, `SameSite`, chiffrées ; **expiration d'inactivité et
  expiration absolue** ; invalidation de toutes les sessions au changement de mot de passe ; liste des appareils
  connectés avec déconnexion des autres.
- **SEC-18.** Un blocage (2FA exigée, abonnement échu) **ne doit pas enfermer** : changer d'espace et quitter un
  espace restent accessibles, et la page dit pourquoi l'accès est fermé.
- **SEC-19.** Le contrôle de 2FA ne se **désactive qu'en développement local**, et un test de déploiement
  vérifie qu'il est actif en production.

## 3. Autorisation, rôles et permissions

- **SEC-20.** **Autoriser chaque route et chaque action** côté serveur. Aucune vérification de rôle faite
  uniquement côté client.
- **SEC-21.** **L'autorisation se joue avant la validation** : dans l'étape d'autorisation de la requête, pas
  seulement dans le contrôleur. Sinon un utilisateur non autorisé reçoit un message de validation qui le
  renseigne sur ce que le formulaire attend.
- **SEC-22.** Les **permissions forment un catalogue fermé déclaré dans le code**. Aucune permission n'est créée
  depuis l'interface : le contrôle d'accès deviendrait falsifiable par saisie.
- **SEC-23.** Les **profils** sont des données composées à partir de ce catalogue. Le profil le plus privilégié
  est **système** : ni modifiable ni supprimable, et il en reste toujours au moins un porteur actif.
- **SEC-24.** **Anti-élévation** : qui gère les profils ne peut ni accorder une permission qu'il ne détient pas,
  ni affecter un profil qui en détient plus que le sien, ni modifier le profil qu'il porte, ni s'en affecter un
  autre. Le contrôle sur la **composition** d'un profil ne suffit pas, il faut le même sur l'**affectation**.
- **SEC-25.** Une modification de profil prend **effet immédiatement** (cache de permissions invalidé).
- **SEC-26.** **Permissions distinctes pour des gestes distincts** : consulter, exporter, modifier l'identité
  légale, modifier la marque, modifier le compte de paiement ne sont pas la même permission.
- **SEC-27.** **404, jamais 403, pour une ressource d'un autre client** : ne pas révéler son existence. Valable
  aussi pour les redirections et les messages de validation.
- **SEC-28.** Un compte d'exploitation (éditeur, support) **n'a aucun accès aux données d'un client** par défaut.
  L'accès du support est **accordé par le client**, limité dans le temps et dans la portée, nommé, journalisé
  côté client, en lecture seule, avec un bandeau visible pendant sa durée.
- **SEC-29.** Toute création, modification, suppression et affectation de profil est **journalisée** avec
  l'acteur, l'avant et l'après.

## 4. Secrets et configuration

- **SEC-30.** **Les secrets vivent dans des variables d'environnement**, jamais dans le dépôt, une conversation,
  un ticket ou un journal. Rotation possible sans redéploiement du code.
- **SEC-31.** Un secret ne se **saisit** que sur le serveur, au prompt, ou se **génère** sur le serveur dans un
  fichier à droits stricts. On ne l'affiche pas, on ne le colle pas dans un outil, on ne le demande jamais à un
  utilisateur dans un message.
- **SEC-32.** Un secret exposé par erreur (conversation, capture d'écran, dépôt) est **considéré compromis** et
  remplacé.
- **SEC-33.** La **clé principale de l'application** (chiffrement, signatures de session) est **copiée hors du
  serveur** (gestionnaire de mots de passe) : sa perte rend illisible tout ce qu'elle chiffre. Elle ne se
  régénère jamais en rejouant une commande d'installation.
- **SEC-34.** Une **rotation** de secrets ne se fait pas dans la même livraison qu'un changement de code.
- **SEC-35.** Un **contrôle de production** automatisé refuse de démarrer si la configuration est dangereuse :
  mode debug actif, URL non sécurisée, niveau de journal bavard, clés de test, identité légale incomplète, 2FA
  désactivée, relecture juridique non faite.
- **SEC-36.** Ne **jamais copier du développement** vers la production : comptes de démonstration, clés d'essai,
  options qui désactivent un contrôle.
- **SEC-37.** Les **proxys de confiance** sont listés (jamais `*`) : sinon l'en-tête `X-Forwarded-For` est
  falsifiable et toute limitation par adresse devient décorative.

## 5. Entrées : validation, injections, requêtes sortantes

- **SEC-40.** **Valider à la frontière** avec des règles explicites. Requêtes **préparées** uniquement,
  échappement par défaut à l'affichage.
- **SEC-41.** **Affectation de masse** : liste explicite des champs remplissables sur chaque modèle ; identifiants
  de client, statuts et montants **jamais** remplissables depuis une requête ; pas de `fill($request->all())`.
- **SEC-42.** **Gabarits édités par les clients** (messages, documents) : substitution de variables par **liste
  blanche** sur une table de correspondance explicite ; **aucun moteur évaluateur** (eval, rendu de gabarit
  serveur) sur une chaîne fournie par un utilisateur, sous peine d'exécution de code. Valeur inconnue : chaîne
  vide, jamais le nom de la variable ni une pile d'erreur. Échappement selon le contexte (HTML pour le courriel,
  texte pour la messagerie). Aucun lien sortant arbitraire.
- **SEC-43.** **Injection de formules dans les exports** : toute valeur commençant par `=`, `+`, `-`, `@`, une
  tabulation ou un retour chariot est préfixée ou refusée. Test avec un jeu de valeurs hostiles.
- **SEC-44.** **Requêtes sortantes initiées par le serveur** (import par URL, webhook sortant, rendu de document
  par navigateur sans tête) : schémas limités à `https`, résolution DNS contrôlée, **refus des plages privées et
  de bouclage** (métadonnées du nuage comprises), protection contre le rebond DNS par vérification après
  résolution, JavaScript et accès réseau désactivés dans le moteur de rendu, aucune ressource distante récupérée.
- **SEC-45.** **Valeurs venues de l'extérieur et utilisées comme chemins ou clés** (code de langue, nom de
  collection, type de document) : confrontées à un **catalogue fermé** avant tout usage. Une valeur inconnue est
  ignorée en silence, ni mémorisée ni cause d'erreur qui révèle l'arborescence.
- **SEC-46.** Valeurs injectées dans des **styles ou attributs** (couleurs de marque) validées par expression
  régulière stricte. Jamais de HTML brut issu d'une donnée (`dangerouslySetInnerHTML` interdit en revue).
- **SEC-47.** **Catalogues fermés** pour tout ce qui figure sur un document à valeur probante (forme juridique,
  motifs de rejet) : pas de texte libre.

## 6. Fichiers déposés

- **SEC-50.** Types acceptés par **liste blanche**, validés sur les **octets** du fichier, pas sur l'extension ni
  sur l'en-tête `Content-Type` déclaré.
- **SEC-51.** **SVG refusé sans exception** (il peut porter du script). PDF : refusé au profit d'une capture
  d'écran, ou converti en image dans un processus isolé, sans réseau, temps et mémoire plafonnés.
- **SEC-52.** **Réencodage systématique** des images : détruit métadonnées (position GPS, appareil), scripts et
  charges utiles, et normalise les dimensions. Ce n'est pas une optimisation, c'est un contrôle.
- **SEC-53.** Le délégué de rendu à risque d'un outil de traitement d'image est **désactivé** (ou on utilise une
  bibliothèque qui n'en dépend pas). Limites de mémoire et de pixels imposées. Défense contre l'image à
  décompression explosive.
- **SEC-54.** Taille plafonnée, cadence limitée par ressource et par adresse. Antivirus si l'hébergement le
  permet.
- **SEC-55.** **Stockage hors de la racine web.** Accès par **URL signée expirante** (quelques minutes pour des
  données personnelles ou financières), `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`,
  idéalement depuis un domaine distinct sans cookie. Un document sensible n'est jamais servi depuis le dossier
  public.
- **SEC-56.** Nom d'un fichier jamais construit à partir de la saisie ; rangement par client
  (`clients/{id}/{collection}/{id}/`) pour que le cloisonnement se voie sur le disque.
- **SEC-57.** Dépôt, consultation et retrait de fichiers sensibles sont **journalisés**.

## 7. Liens publics, jetons, énumération

- **SEC-60.** **Aucun identifiant séquentiel devinable** dans une adresse publique : jetons aléatoires de **32
  octets au minimum**.
- **SEC-61.** Un jeton de reprise ou d'accès est **stocké haché**, comparé **en temps constant**, à usage limité
  et **expirant**. Le seul moyen de le recalculer est d'avoir la clé, jamais de le lire en base.
- **SEC-62.** Un jeton qu'un système doit **recalculer plus tard** (liens inclus dans des rappels envoyés à
  retardement) est dérivé par **HMAC** de l'identifiant et d'une clé, jamais stocké en clair, vérifié par
  comparaison en temps constant ; un lien altéré reçoit la même réponse qu'un lien inconnu.
- **SEC-63.** **Référence métier affichée ≠ identifiant technique.** Les références séquentielles sont devinables
  et invitent à l'accès direct non autorisé (IDOR).
- **SEC-64.** **Réponses uniformes** (contenu, code, temps) que la ressource existe ou non. Un jeton inconnu, une
  ressource jamais publiée et un sous-domaine inconnu répondent **la même chose**.
- **SEC-65.** Un lien envoyé n'est **pas** un jeton en clair écrit dans une file ou une base : envoi immédiat, hors
  file, quand le jeton ne doit pas être persisté.
- **SEC-66.** Ce qui est **déjà distribué** (adresses, jetons, billets) ne change plus : fige le sous-domaine, ne
  fait jamais tourner un jeton public.
- **SEC-67.** **Retrouver un accès perdu** ne révèle rien : le lien est envoyé **au contact enregistré**, jamais à
  celui qu'on saisit, avec une réponse identique qu'un message parte ou non.

## 8. Limitation de débit et anti-robot

- **SEC-70.** **Limiteurs nommés** (pas le réglage par défaut) sur tout point d'entrée abusable : connexion, 2FA,
  lien public, création de ressource réservée, dépôt de pièce, reprise de lien, scan, exports, import.
- **SEC-71.** **Choisir la clé du limiteur avec soin** : compte plus adresse, session, jeton plus adresse,
  adresse et sous-réseau. Une clé trop large punit des utilisateurs légitimes derrière la même adresse (réseaux
  mobiles partagés) ; trop étroite, elle ne protège rien.
- **SEC-72.** **Un refus de validation ne compte pas** dans les plafonds d'acceptation (sinon on punit les fautes
  de frappe) ; un plafond plus large compte tout.
- **SEC-73.** **Toute page qui interroge le serveur seule** (sondage, rechargement) a **son propre limiteur** et
  sort du limiteur général. Compter ses requêtes contre chaque limiteur de sa route avant de l'ajouter.
- **SEC-74.** Séparer la **lecture** d'un lien des **gestes** qui agissent : leurs plafonds diffèrent.
- **SEC-75.** **Anti-robot** sur les formulaires publics qui bloquent une ressource : vérification d'un moyen de
  contact par code à usage unique avant de réserver, une réservation active par contact, plafond par adresse et
  sous-réseau, retrait progressif après expirations répétées, défi anti-robot au-delà d'un seuil. **Décider
  explicitement** de la panne du service anti-robot (laisser passer en journalisant, ou fermer).
- **SEC-76.** Les réglages de protection que l'éditeur peut changer (plafonds, durées) ont des **bornes**, ne sont
  jamais retirables, et chaque changement est journalisé.
- **SEC-77.** Un **tableau de bord** montre le taux de ressources expirées ou refusées, pour rendre une attaque
  de saturation visible.

## 9. Transport, en-têtes, navigateur

- **SEC-80.** **CSRF** actif sur toute mutation, y compris les formulaires publics (jeton plus champ piège).
  `SameSite=Lax` ne suffit pas seul. Un webhook entrant est exempté **uniquement** pour son chemin exact, avec
  **vérification de signature**.
- **SEC-81.** **CSP à nonces**, `unsafe-inline` interdit : `default-src 'self'`, `object-src 'none'`,
  `base-uri 'self'`, `frame-ancestors 'none'`. Les domaines tiers n'entrent dans la CSP que **si le service est
  activé**.
- **SEC-82.** HSTS, `X-Content-Type-Options`, `X-Frame-Options` (ou équivalent CSP), `Referrer-Policy`,
  `Permissions-Policy` restrictive. `Referrer-Policy: no-referrer` et `Cache-Control: no-store` sur les routes de
  documents sensibles.
- **SEC-83.** Un cadre d'interface qui passe son état initial dans un attribut de page (Inertia, Next) impose de
  **ne pas** affaiblir la CSP pour l'accommoder.
- **SEC-84.** Un contenu accessible depuis un **domaine d'une autre origine** (cookies, hôtes) ne partage ni
  cookie ni stockage avec l'application.

## 10. Données sensibles qui voyagent

- **SEC-90.** **Props et réponses** : objets de transfert explicites, revue systématique sur les écrans
  sensibles (preuves, équipe, abonnement, journal). Voir CODE-22.
- **SEC-91.** **Exports** : permission distincte de la consultation ; **plafond par utilisateur et par heure**
  réglable mais jamais retirable ; journalisation du nombre de lignes et des filtres ; alerte au propriétaire
  au-delà d'un seuil ; filigrane (identité du demandeur, horodatage) sur les exports de document.
- **SEC-92.** **Journaux applicatifs et télémétrie** : masquer jetons, codes à usage unique, numéros de
  téléphone, références complètes. Aucun corps de requête capturé sur les routes de dépôt de pièce. Données
  anonymisées avant envoi à un service de suivi d'erreurs. Fichier de journal **par jour, durée bornée**, niveau
  `warning` en production.
- **SEC-93.** **Mesure d'audience et traceurs** : seulement sur les pages publiques commerciales, **après
  consentement**, sans paramètres d'adresse, jamais sur le back-office ni sur des adresses qui portent des jetons.
- **SEC-94.** **Application hors ligne (PWA)** : aucun jeton d'authentification en `localStorage` ; effacement de
  la file locale et des caches à la déconnexion et à la clôture ; contenu hors ligne réduit au strict nécessaire ;
  **verrouillage de l'écran sensible par code** après inactivité (sans réseau aussi).
- **SEC-95.** **Données personnelles minimales** : ne collecter, conserver et afficher que ce qui sert. Voir
  `08-confidentialite-et-conformite.md`.

## 11. Journal d'audit

- **SEC-100.** **Écriture seule** : l'utilisateur applicatif n'a pas `UPDATE` ni `DELETE` sur la table d'audit.
- **SEC-101.** Chaque entrée porte : **acteur, action, ressource, avant, après, adresse, agent utilisateur**,
  date.
- **SEC-102.** **Chaînage par empreinte** de l'entrée précédente : une suppression ou une modification devient
  **détectable**. Une vérification quotidienne signale la rupture. Les champs à effacer légalement (adresse IP)
  **sont exclus du calcul** de l'empreinte pour que l'effacement ne casse pas la chaîne.
- **SEC-103.** Conservation **bornée** (24 mois par exemple), purge automatisée et tracée. Export périodique
  vers un stockage en écriture seule.
- **SEC-104.** **Plusieurs journaux, chacun à sa place** : actions de l'équipe d'un client, événements de
  sécurité de la plateforme, faits côté utilisateur final. Chaque journal dit ce qu'il contient et combien de
  temps.
- **SEC-105.** **Journaliser les gestes qui se contournent discrètement** : modification d'un compte de paiement,
  de l'identité légale, d'un profil, export, consultation d'une pièce, accès du support, entrée sans contrôle.
- **SEC-106.** Un geste sensible **alerte** les personnes concernées (changement de compte de paiement, entrée
  forcée, export massif, accès du support).

## 12. Comptes critiques (argent, identité)

Le détournement d'un numéro de paiement affiché à des tiers est le scénario le plus coûteux d'un produit qui
encaisse. Contrôles :

- **SEC-110.** Permission **dédiée**, jamais incluse dans un profil prédéfini autre que le plus privilégié.
- **SEC-111.** **Re-authentification forte** immédiatement avant la modification, indépendamment de la session.
- **SEC-112.** **Délai d'activation** (24 h par exemple) à partir du moment où des tiers peuvent y verser :
  l'ancienne valeur reste affichée pendant le délai. Avant ce moment, l'activation est immédiate après un aperçu
  et une confirmation explicite.
- **SEC-113.** **Deuxième regard** : un autre porteur du profil le plus privilégié peut valider ou écourter ;
  **jamais** la personne qui a demandé le changement.
- **SEC-114.** **Alerte immédiate** à tous les porteurs de la permission (courriel plus messagerie), avec l'ancienne
  et la nouvelle valeur. Sans alerte, personne ne regarde pendant le délai.
- **SEC-115.** Un compte **tout juste créé n'a pas de valeur active** pendant le délai (sinon il suffirait d'ajouter
  un compte plutôt que d'en modifier un). Un changement reste **signalé** pendant une semaine.
- **SEC-116.** **Aucune modification par import, API ou webhook** : seulement depuis l'écran, avec re-authentification.
- **SEC-117.** L'activation est appliquée par **une tâche planifiée**, seul endroit qui bascule la valeur en
  attente. Si la tâche ne tourne pas, l'ancienne valeur reste : c'est le bon sens de défaillance.
- **SEC-118.** **Identité légale et sous-domaine** : mêmes exigences (permission dédiée, journal avant/après,
  re-authentification), normalisation avant validation, liste d'adresses **réservées**.

## 13. Signatures et accès hors ligne

- **SEC-120.** Quand un appareil doit **vérifier sans réseau** : signature **asymétrique** (Ed25519). Le serveur
  signe, l'appareil ne détient que la clé publique. Une signature à clé partagée (HMAC) met le secret dans
  chaque appareil perdu.
- **SEC-121.** Clé privée dans un coffre (KMS, HSM) ou chiffrée par la clé principale, jamais dans le dépôt ni
  lisible par le client.
- **SEC-122.** Le jeton porte **client, ressource, identifiant, nonce, expiration, version de clé**. Rotation de
  clé par ressource ; tout expire à la clôture. Vérification **stricte** (un caractère de fin invalide le jeton).
- **SEC-123.** **Anti-double usage** : détection locale pendant la session hors ligne, détection serveur à la
  synchronisation, rapport des conflits. **Liste de révocation signée**, rechargée à chaque retour du réseau.
- **SEC-124.** Un forçage (passer outre un refus) est une **permission**, **tracée** et signalée.

## 14. Webhooks

- **SEC-130.** Un webhook entrant vérifie la **signature du fournisseur** (en temps constant) avant tout, est
  exempté de CSRF sur son chemin exact seulement, a son limiteur.
- **SEC-131.** Test avec signature valide **et** invalide ; réponse **neutre** (403) sans bon jeton.
- **SEC-132.** Le contenu du webhook est **effacé dès son traitement** quand il est personnel ; les appels en échec
  sont purgés après une durée bornée.
- **SEC-133.** Un webhook ne modifie **jamais** un compte de paiement, un rôle ou un secret.
- **SEC-134.** Traiter de façon **idempotente** : le fournisseur renvoie les mêmes événements.

## 15. Dépendances, erreurs, sauvegardes

- **SEC-140.** Audit des dépendances **bloquant** en intégration ; mises à jour de sécurité suivies ; une version
  de langage ou de système **à jour** est une mesure de sécurité (la mise à jour du serveur d'exécution corrige
  des failles). Si un serveur a tourné sur une version vulnérable, **étudier** la rotation des secrets.
- **SEC-141.** `DEBUG=false` en production, vérifié par un test de déploiement. Aucune pile, aucun nom de table
  dans une réponse. Pages d'erreur rédigées pour l'utilisateur. Un 404 **attendu** (sous-domaine inconnu) ne
  pollue pas le journal d'erreurs.
- **SEC-142.** **Sauvegardes chiffrées**, avec des identifiants **distincts** de ceux de l'application, vers un
  stockage **extérieur au serveur**, avec **restauration testée** et documentée. Sur le seul serveur de
  l'application, une sauvegarde protège d'une erreur de manipulation, pas de la perte du serveur.
- **SEC-143.** La base de données n'est **jamais exposée à Internet** : port fermé ou restreint au réseau des
  conteneurs, `listen_addresses` et règles d'accès revues. Un `0.0.0.0/0` est un incident.
- **SEC-144.** Un **rôle de base applicatif** a les droits dont il a besoin et **n'est pas super-utilisateur**.
- **SEC-145.** **Isolation entre applications d'un même serveur** : une application ne doit pouvoir casser ni
  lire l'autre (fichiers de configuration séparés, bases et rôles à part, aucun redémarrage commun inutile).
- **SEC-146.** Rien ne fonctionne en **tâche planifiée ou commande sans contexte de client résolu** sans
  refuser de s'exécuter (voir `05-donnees-concurrence-multi-organisation.md`).

## 16. Erreurs fréquentes à ne pas refaire

| Erreur | Conséquence |
| --- | --- |
| Faire confiance à `X-Forwarded-For` | Limitation par adresse contournée |
| Vérifier l'extension d'un fichier | Script renommé en image accepté |
| Garder un SVG « pour le logo » | Exécution de script dans la session de l'administrateur |
| Autoriser après la validation | Fuite d'information par les messages d'erreur |
| Un 403 pour une ressource d'un autre client | Existence révélée |
| Un jeton de reprise stocké en clair | Lu en base = compte pris |
| Journal modifiable | Aucune preuve en cas d'incident |
| Clé HMAC dans l'appareil hors ligne | Faux billets indétectables |
| Désactiver un contrôle « seulement en local » sans le tester en production | Contrôle absent en production |
| Un export sans plafond ni alerte | Fuite par un compte légitime |
