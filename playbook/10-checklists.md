# 10. Listes de contrôle

Des listes courtes à dérouler au bon moment. Chaque ligne renvoie à une règle des autres fichiers. Une case non
cochée est un point **supposé faux**.

## 1. Avant de commiter

- [ ] Le changement fait **une seule chose** (PRI-06, GIT-01).
- [ ] Un **test** couvre le comportement ; un correctif de bogue a son test qui reproduit le bogue (TEST-02).
- [ ] Les tests du fichier touché et des modules voisins ont tourné ; ce qui n'a pas tourné est écrit (PRI-08).
- [ ] Formatage et analyse statique **propres** sur les fichiers touchés (CODE-40, CODE-41, CODE-42).
- [ ] Les **textes** sont dans **toutes** les langues (I18N-04).
- [ ] La **documentation** et la **version des textes légaux** avancent avec le comportement (PRIV-03, OPS-05).
- [ ] Aucun **secret**, fichier d'environnement, dump ni donnée réelle (GIT-03).
- [ ] Aucun fichier sans rapport (sorties de campagne, brouillons) n'est ajouté (GIT-06).
- [ ] Le message de commit dit **quoi et pourquoi**, sans emoji.

## 2. Nouvelle route ou nouveau point d'entrée

- [ ] **Autorisation** vérifiée avant la validation (SEC-21), politique dédiée (CODE-12).
- [ ] **Chaque rôle** testé autorisé et refusé ; un client tiers reçoit **404** (TEST-10, SEC-27).
- [ ] **Validation** par un objet dédié, **affectation de masse** impossible (SEC-41).
- [ ] **Limiteur nommé** si la route est abusable (SEC-70) ; clé choisie avec soin (SEC-71).
- [ ] **Idempotente** si rejouable (DATA-25).
- [ ] **Journal d'audit** si le geste est sensible (SEC-105), alerte si nécessaire (SEC-106).
- [ ] La réponse ne transmet **que** les champs prévus (CODE-22).
- [ ] Un lien y mène depuis la navigation (UI-30).
- [ ] Les **erreurs** sont des réponses utiles, pas des exceptions (CODE-50, CODE-51).

## 3. Nouvel écran

- [ ] Tous les **états** : chargement, vide, erreur, succès, permission refusée, hors ligne (UI tableau §2).
- [ ] **Composants** de la bibliothèque, pas de primitive maison (UI-01, UI-02).
- [ ] **Confirmation** pour tout geste difficile à annuler (UI-20 à UI-24).
- [ ] **Champs obligatoires** signalés d'après la validation serveur (UI-44).
- [ ] **Accessibilité** : clavier, focus, contraste, couleur jamais seule, mouvement réduit (UI-50 à UI-54).
- [ ] **Mobile** : pas de largeur fixe, cibles de 44 px (UI-51, UI-60).
- [ ] **Listes** paginées, filtrées et triées côté serveur (DATA-70).
- [ ] **Textes** traduits, sans jargon ni redondance (I18N-04, UI-40, UI-42).
- [ ] **Visite guidée** ajoutée si l'écran est complexe (UI-34).

## 4. Nouvelle migration

- [ ] Elle **ajoute** : rien n'est renommé ni supprimé (DATA-01).
- [ ] Le **code précédent** continue de fonctionner pendant son exécution (DATA-02).
- [ ] **Portable** entre les moteurs supportés (DATA-06) ; testée sur celui de production si elle touche aux
      transactions ou aux verrous (DATA-11).
- [ ] **Index** et **contraintes** posés (DATA-07) ; cascades pensées (DATA-08).
- [ ] La **reprise de données** existantes se fait par lots (DATA-04).
- [ ] Une migration par client et une centrale : **placée au bon endroit** (DATA-31).
- [ ] Le **plan de retour** est la sauvegarde prise avant (DATA-03).

## 5. Nouvelle dépendance ou nouveau service

- [ ] **Alternatives comparées**, accord du responsable obtenu **avant** l'installation (PRI-09, CODE-61).
- [ ] Service tiers : **vérifié** (société, avis), prix confirmés, disponibilité locale (EXT-01 à EXT-04).
- [ ] **Interface** et pilote de journal d'abord (EXT-10, EXT-11).
- [ ] **Aucun appel réel en test** (EXT-18).
- [ ] Mentionné dans la **politique de confidentialité** s'il reçoit des données personnelles (PRIV-12).
- [ ] Ajouté à l'**inventaire** sans secret (EXT §4) et au **contrôle de production** (EXT cycle §6).
- [ ] **Panne** décidée : fermer ou laisser passer (EXT-17).

## 6. Avant de livrer

- [ ] Le code est sur la branche principale distante ; **commit en service noté** (OPS §2).
- [ ] **Sauvegarde** prise, surtout si le schéma change.
- [ ] **Version** choisie, **textes légaux** à jour.
- [ ] Le **contrôle de production** passe.
- [ ] Une seule chose est livrée (OPS-02).
- [ ] Les **migrations** s'enchaînent avec arrêt au premier échec (OPS-07).
- [ ] **Après** : services sains, pages en 200, journaux sans erreur, santé technique au vert, écran modifié
      vérifié dans le navigateur.
- [ ] **Retour arrière** prêt : commit noté et sauvegarde.

## 7. Avant d'ouvrir au public

Voir `07-livraison-et-exploitation.md` §10. En résumé :

- [ ] Contrôle de production sans erreur.
- [ ] **Suite complète** sur le moteur de production, tests d'interface et de bout en bout.
- [ ] **Grille de sécurité** (§8 ci-dessous) rejouée.
- [ ] **Restauration** éprouvée ; sauvegardes externes chiffrées.
- [ ] **Clé principale copiée** hors du serveur ; **second administrateur** de console.
- [ ] **Textes légaux relus** par un juriste ; identité de l'éditeur renseignée.
- [ ] **Essai réel** de bout en bout sur un vrai téléphone.
- [ ] **Limiteurs** vérifiés en conditions réelles.
- [ ] Ouverture **progressive** : pilote, surveillance, puis extension.

## 8. Grille de sécurité à rejouer

**Cloisonnement.**

- [ ] Pour chaque route du back-office, requête d'un client tiers : **404** (TEST-10, TEST-11).
- [ ] Requête forcée **sans** filtre : la donnée n'existe pas sur la connexion.
- [ ] Job ou tâche **sans client** : échec, pas lecture silencieuse (DATA-33).
- [ ] Jeton public présenté sous le sous-domaine d'un autre client : 404 **dans les deux sens** (DATA-35).

**Concurrence et état.**

- [ ] Deux requêtes sur la dernière unité : une seule aboutit ; cent en parallèle : aucune survente (TEST-12).
- [ ] Action après expiration : refusée ; purge : unités rendues, journal écrit (TEST-15).
- [ ] Double validation d'un même objet : une seule prise en compte (DATA-23).
- [ ] Transition depuis un état inattendu : échec (DATA-24).

**Fichiers déposés.**

- [ ] SVG avec script : refusé. PDF avec script : refusé ou neutralisé. Polyglotte image et HTML : refusé.
- [ ] Image à décompression explosive : refusée. Extension falsifiée : refusée.
- [ ] Métadonnées après réencodage : **absentes**. Fichier atteignable sans URL signée : impossible (SEC-50 à SEC-55).

**Jetons et liens.**

- [ ] Jeton modifié d'un octet, d'un autre contexte, expiré, rejoué : refusé (TEST-18).
- [ ] Appareil hors ligne compromis : incapable de forger un jeton (SEC-120).
- [ ] Lien inconnu, lien altéré, ressource jamais publiée : **même réponse** (SEC-64).

**Argent et comptes critiques.**

- [ ] Modification sans re-authentification : refusée. Sans la permission dédiée : refusée.
- [ ] Validation du délai par le demandeur : refusée ; par un non-privilégié : refusée.
- [ ] Alerte et délai d'activation **effectifs** (SEC-112 à SEC-114).

**Profils et permissions.**

- [ ] Attribution d'une permission non détenue : refusée. Affectation d'un profil plus puissant : refusée.
- [ ] Modification de son propre profil : refusée. Suppression du dernier privilégié : refusée.
- [ ] Profil exigeant la 2FA sans 2FA : back-office fermé ; contrôle **actif en production** (SEC-19).

**Débit et robots.**

- [ ] Chaque limiteur atteint, y compris avec un `X-Forwarded-For` falsifié (TEST-16).
- [ ] Réservations en boucle : bloquées avant épuisement de la ressource (SEC-75).

**Exposition.**

- [ ] Props inspectées sur les écrans sensibles (SEC-90).
- [ ] Export avec valeurs hostiles : formules neutralisées (SEC-43).
- [ ] URL signée réutilisée après expiration : refusée (SEC-55).
- [ ] En-têtes de sécurité présents, CSP sans `unsafe-inline` (SEC-81, SEC-82).
- [ ] `DEBUG` désactivé en production, aucune pile dans une réponse (SEC-141).

## 9. Correction d'un bogue

- [ ] Test qui **reproduit** le bogue, **rouge**, écrit d'abord (TEST-02).
- [ ] Cause **comprise** avant d'écrire le correctif (pas de correctif à l'aveugle).
- [ ] Le même défaut existe-t-il **ailleurs** ? (recherche du même motif dans le code).
- [ ] Les tests voisins repassent ; aucun contrôle affaibli (PRI-05).
- [ ] Si le bogue venait d'un **piège d'environnement**, il est ajouté à la liste des pièges (TEST-50 à TEST-54,
      OPS §9).

## 10. Incident en production

1. **Stabiliser** : un retour arrière (code) ou une restauration (données), selon `07-livraison-et-exploitation.md`.
2. **Ne pas improviser sur le serveur** : si une action hors procédure est nécessaire, la **consigner**.
3. **Comprendre** : journaux, journal d'audit, journal de sécurité, écran de santé.
4. **Évaluer l'exposition** : des données ont-elles été lues, modifiées ? Des secrets sont-ils exposés ? Si oui,
   rotation (SEC-32, SEC-140) et information (PRIV-50).
5. **Corriger avec un test** (§9), livrer selon la procédure.
6. **Écrire** ce qui s'est passé, la cause, ce qui a été changé pour que ça ne revienne pas.
