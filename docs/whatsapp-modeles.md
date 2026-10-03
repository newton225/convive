# Modèles de messages WhatsApp

WhatsApp n'autorise un message libre que dans les 24 heures qui suivent un message de la personne.
Presque tous les envois de Convive partent hors de cette fenêtre : chacun doit donc passer par un
**modèle approuvé**. On le crée chez Twilio (« Content Template Builder ») ou chez Meta (« Modèles de
message »), on attend l'approbation, puis on renseigne son identifiant dans le réglage indiqué.

- **Twilio** : l'identifiant commence par `HX` (« Content SID »).
- **Meta** : c'est le nom donné au modèle, par exemple `convive_rappel_preuve`.

Passer de Twilio à Meta ne demande aucun changement de code : il faut recréer les modèles chez Meta,
avec les mêmes textes, puis changer `WHATSAPP_DRIVER` et les réglages ci-dessous.

Les variables `{{1}}`, `{{2}}`... doivent rester **dans cet ordre** : c'est l'ordre dans lequel
l'application les envoie. Meta refuse un modèle qui commence ou finit par une variable ; les textes
ci-dessous en tiennent compte. Il refuse aussi une variable vide ou sur plusieurs lignes :
l'application ramène chaque variable sur une seule ligne et n'en laisse aucune vide.

**Langue : français** (décision du propriétaire du projet, 2026-10-03). Chaque modèle se crée en
français (`fr`) seulement, y compris pour les invités inscrits en anglais. Ajouter l'anglais
demanderait de retenir la langue de chaque invité et de créer chaque modèle une seconde fois.

Textes vérifiés le 2026-10-03 contre les messages que l'application envoie réellement.

**Soumis chez Meta le 2026-10-03** (compte de test, par l'API) : `convive_carte_invitation`,
`convive_rappel_preuve`, `convive_rappel_jour_j`, `convive_inscription_annulee`,
`convive_remboursement`, `convive_alerte_compte`. Le modèle du code de vérification est **refusé** :
Meta réserve la catégorie « authentification » aux entreprises vérifiées. Le code passera par SMS
(décision du propriétaire du projet, même jour) ; d'ici là, la règle « vérification du téléphone »
d'un événement reste à désactiver.

## Carte d'invitation

- Réglage : `WHATSAPP_TEMPLATE_INVITATION_CARD`
- Catégorie : utilitaire
- Variables : `{{1}}` nom de l'invité, `{{2}}` nom de l'événement, `{{3}}` lien du billet

> Bonjour {{1}}, votre inscription à {{2}} est confirmée. Votre billet : {{3}} Les billets de vos éventuels accompagnateurs sont sur la même page. À bientôt !

Les billets des accompagnateurs ne peuvent pas figurer dans le modèle (leur nombre varie) : l'invité
les retrouve sur la page de son billet, d'où il peut les transmettre.

## Code de vérification du téléphone

- Réglage : `WHATSAPP_TEMPLATE_PHONE_CODE`
- Catégorie : authentification
- Variable : `{{1}}` le code

Meta et Twilio imposent leur propre texte pour cette catégorie (« {{1}} est votre code de
vérification »). Options à choisir à la création chez Meta :

- bouton **« Copier le code »** (pas « remplissage automatique ») : l'application envoie le code
  pour ce bouton ;
- **délai d'expiration : 10 minutes**, la durée de validité du code dans l'application ;
- avertissement de sécurité (« Pour votre sécurité, ne communiquez pas ce code ») : à cocher.

## Rappel de preuve de paiement

- Réglage : `WHATSAPP_TEMPLATE_PROOF_REMINDER`
- Catégorie : utilitaire
- Variables : `{{1}}` nom de l'invité, `{{2}}` nom de l'événement, `{{3}}` lien vers son inscription

> Bonjour {{1}}, il manque votre preuve de paiement pour {{2}}. Envoyez-la ici : {{3}} Merci !

## Rappel du jour J

- Réglage : `WHATSAPP_TEMPLATE_TICKET_REMINDER`
- Catégorie : utilitaire
- Variables : `{{1}}` nom de l'invité, `{{2}}` nom de l'événement, `{{3}}` lien du billet

> Bonjour {{1}}, {{2}} a lieu dans trois heures. Votre billet : {{3}} À tout à l'heure !

## Inscription annulée

- Réglage : `WHATSAPP_TEMPLATE_REGISTRATION_CANCELLED`
- Catégorie : utilitaire
- Variables : `{{1}}` nom de l'invité, `{{2}}` nom de l'événement, `{{3}}` motif de l'annulation,
  `{{4}}` ce que devient le paiement

> Bonjour {{1}}, votre inscription à {{2}} a été annulée par l'organisation. Motif : {{3}}. {{4}} Pour toute question, contactez l'organisateur.

`{{4}}` est l'une de ces phrases : « Votre paiement de 60 000 F CFA vous sera remboursé :
l'organisation vous préviendra dès que ce sera fait. », « Vous avez reçu ... le ... par ... (frais de
transaction de ... déduits). », « Votre paiement n'est pas remboursé. Motif : ... », ou « Aucun
paiement n'avait été encaissé pour cette inscription. »

## Remboursement envoyé

- Réglage : `WHATSAPP_TEMPLATE_REFUND_SENT`
- Catégorie : utilitaire
- Variables : `{{1}}` nom de l'invité, `{{2}}` nom de l'événement, `{{3}}` montant reçu, `{{4}}` date,
  `{{5}}` moyen (Wave, Orange Money...), `{{6}}` frais de transaction déduits

> Bonjour {{1}}, votre remboursement pour {{2}} est parti : vous avez reçu {{3}} le {{4}} par {{5}}, frais de transaction de {{6}} déduits. Merci de votre compréhension.

## Alerte : compte de versement modifié

Envoyé aux membres de l'organisation, pas aux invités.

- Réglage : `WHATSAPP_TEMPLATE_PAYMENT_ACCOUNT_CHANGED`
- Catégorie : utilitaire
- Variables : `{{1}}` nom de l'organisation, `{{2}}` libellé du compte, `{{3}}` ancien numéro, `{{4}}` nouveau numéro

> Convive : le compte de versement « {{2}} » de {{1}} a changé. Ancien numéro : {{3}}. Nouveau : {{4}}. Si vous n'êtes pas à l'origine de cette demande, annulez-la immédiatement.
