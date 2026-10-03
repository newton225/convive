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
ci-dessous en tiennent compte.

## Carte d'invitation

- Réglage : `WHATSAPP_TEMPLATE_INVITATION_CARD`
- Catégorie : utilitaire
- Variables : `{{1}}` nom de l'invité, `{{2}}` nom de l'événement, `{{3}}` lien du billet

> Bonjour {{1}}, votre inscription à {{2}} est confirmée. Votre billet : {{3}} À bientôt !

Les billets des accompagnateurs ne figurent pas dans ce modèle (leur nombre varie) : l'invité les
retrouve sur sa page, et chacun peut les transmettre depuis le lien de son billet.

## Code de vérification du téléphone

- Réglage : `WHATSAPP_TEMPLATE_PHONE_CODE`
- Catégorie : authentification
- Variable : `{{1}}` le code

Meta et Twilio imposent leur propre texte pour cette catégorie (« {{1}} est votre code de
vérification »), avec un bouton pour copier le code. Réglez la durée de validité à 10 minutes dans
les options du modèle.

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
- Variables : `{{1}}` nom de l'invité, `{{2}}` nom de l'événement, `{{3}}` motif de l'annulation

> Bonjour {{1}}, votre inscription à {{2}} a été annulée par l'organisation. Motif : {{3}}. Pour toute question, contactez l'organisateur.

## Remboursement envoyé

- Réglage : `WHATSAPP_TEMPLATE_REFUND_SENT`
- Catégorie : utilitaire
- Variables : `{{1}}` nom de l'invité, `{{2}}` nom de l'événement

> Bonjour {{1}}, votre remboursement pour {{2}} est parti. Le détail est sur votre page d'inscription.

## Alerte : compte de versement modifié

Envoyé aux membres de l'organisation, pas aux invités.

- Réglage : `WHATSAPP_TEMPLATE_PAYMENT_ACCOUNT_CHANGED`
- Catégorie : utilitaire
- Variables : `{{1}}` nom de l'organisation, `{{2}}` libellé du compte, `{{3}}` ancien numéro, `{{4}}` nouveau numéro

> Convive : le compte de versement « {{2}} » de {{1}} a changé. Ancien numéro : {{3}}. Nouveau : {{4}}. Si vous n'êtes pas à l'origine de cette demande, annulez-la immédiatement.
