<?php

return [
    'title' => 'Comptes de versement',
    'description' => "Les comptes sur lesquels vos invités versent. Toute modification n'apparaît publiquement qu'après un délai de :hours heures.",

    'description_before_publication' => "Les comptes sur lesquels vos invités verseront. Tant qu'aucun événement n'est publié, une modification s'applique tout de suite, après relecture. Dès la première publication, toute création ou modification attendra :hours heures avant d'être visible.",

    'confirm_immediate' => [
        'title' => 'Vérifier les coordonnées',
        'description' => "Aucun événement n'est encore publié : ces coordonnées s'appliquent dès maintenant. Vérifiez-les, ce sont celles que vos invités verront pour verser.",
        'confirm' => 'Confirmer et enregistrer',
    ],

    'form' => [
        'pending_hint' => "Le formulaire montre votre demande en attente. Corrigez-la ici sans toucher aux coordonnées que vos invités voient encore ; remettre les anciennes valeurs annule la demande.",
    ],

    'channels' => [
        'wave' => 'Wave',
        'orange_money' => 'Orange Money',
        'mtn_money' => 'MTN MoMo',
        'moov_money' => 'Moov Money',
        'bank_transfer' => 'Virement bancaire',
        'cash' => 'Espèces',
    ],

    'fields' => [
        'label' => 'Libellé',
        'label_placeholder' => 'Par exemple : Wave principal',
        'channel' => 'Canal',
        'account_number' => 'Numéro du compte',
        'holder_name' => 'Titulaire du compte',
        'instructions' => 'Consigne affichée à vos invités',
        'instructions_placeholder' => 'Mettez votre nom en motif du transfert.',
        'is_active' => 'Proposé aux invités',
    ],

    'help' => [
        'label' => 'Un nom libre pour reconnaître ce compte. Il ne dit pas à quel réseau le compte appartient : c’est le canal qui fait foi.',
        'channel' => 'Le réseau ou le moyen de paiement. C’est lui que voit l’invité et qui sert au rapprochement du relevé. Le changer déclenche le délai de sécurité de 24 heures.',
        'account_number' => 'Le numéro sur lequel vos invités versent l’argent. Une modification n’est active qu’après 24 heures : l’ancien numéro reste affiché d’ici là, et les responsables des comptes sont prévenus aussitôt.',
        'holder_name' => 'Le nom que l’invité voit au moment du transfert : il lui permet de vérifier qu’il paie la bonne personne. Soumis au même délai de 24 heures.',
        'instructions' => 'Une consigne courte affichée sous le numéro, par exemple le motif à indiquer. Elle s’applique tout de suite.',
    ],

    'actions' => [
        'create' => 'Ajouter un compte',
        'save' => 'Demander la modification',
        'approve' => 'Valider et appliquer maintenant',
        'cancel_change' => 'Annuler la modification',
        'cancel_creation' => 'Annuler la création',
        'delete' => 'Supprimer le compte',
    ],

    'badges' => [
        'visible' => 'Visible par les invités',
        'hidden' => 'Non visible',
        'pending' => 'Modification en attente',
        'recent_change' => 'Modifié récemment',
    ],

    'pending' => [
        'title' => 'Modification en attente',
        'requested_by' => 'Demandée par :name.',
        'activates_at' => "Elle s'appliquera le :date. D'ici là, l'ancien numéro reste affiché.",
        'new_number' => 'Nouveau numéro : :value',
        'activates_at_new' => 'Il sera proposé aux invités le :date.',
        'none' => 'aucun',
        'approve_hint' => "Un second Propriétaire peut l'appliquer immédiatement.",
        'not_the_requester' => 'Vous ne pouvez pas valider une modification que vous avez demandée vous-même.',
    ],

    'notice' => [
        'recent_change' => 'Un compte de versement a été modifié il y a moins de :days jours. Vérifiez que ce changement est légitime.',
    ],

    'flash' => [
        'change_requested' => "Modification enregistrée. Elle s'appliquera après le délai d'activation.",
        'updated' => 'Compte mis à jour.',
        'change_approved' => 'Modification appliquée.',
        'change_cancelled' => 'Modification annulée.',
        'creation_cancelled' => 'Création du compte annulée.',
        'deleted' => 'Compte supprimé.',
    ],

    'errors' => [
        'confirmation_required' => 'Relisez les coordonnées et confirmez-les : elles seront visibles tout de suite par vos invités.',
        'has_proofs' => 'Des invités ont déjà versé sur ce compte : il ne peut pas être supprimé. Désactivez-le pour qu’il ne soit plus proposé.',
        'account_number_required' => 'Ce canal exige un numéro de compte.',
        'number_invalid' => 'Saisissez un numéro ivoirien à 10 chiffres, par exemple 07 07 12 34 56.',
        'number_network' => 'Un numéro :channel commence par :prefixes. Vérifiez le numéro ou le canal choisi.',
    ],

    'confirm_approve' => [
        'title' => 'Activer ce changement maintenant ?',
        'description' => "Le nouveau numéro du compte \":label\" s'affichera tout de suite sur les liens publics, sans attendre la fin du délai de 24 heures. Vérifiez-le auprès de la personne qui l'a demandé, par un autre canal, avant de confirmer.",
    ],

    'confirm_cancel' => [
        'change_title' => 'Annuler la modification ?',
        'change_description' => 'Le nouveau numéro :number ne sera jamais appliqué au compte ":label". L\'ancien numéro reste affiché à vos invités.',
        'creation_title' => 'Annuler la création du compte ?',
        'creation_description' => 'Le compte ":label" n\'a encore jamais été proposé à vos invités : il sera supprimé. Pour un autre numéro, ajoutez un nouveau compte.',
    ],

    'confirm' => [
        'current' => 'Actuellement',
        'new' => 'Après validation',
        'requested_by' => 'Demandée par',
        'activates_at' => 'Prévue le',
        'visibility' => 'Visibilité',
    ],

    'confirm_delete' => [
        'title' => 'Supprimer le compte de versement',
        'description' => 'Le compte ":name" sera supprimé. Cette action est définitive.',
    ],

    'mail' => [
        'subject' => 'Compte de versement modifié : :tenant',
        'intro' => ':actor a demandé la modification du compte ":label" de :tenant.',
        'before' => 'Ancien numéro : :value',
        'after' => 'Nouveau numéro : :value',
        'activates_at' => 'Cette modification prendra effet le :date.',
        'none' => 'aucun',
        'outro' => "Si vous n'êtes pas à l'origine de cette demande, annulez-la immédiatement et vérifiez les accès de votre organisation.",
        'applied_now' => "Votre organisation n'a encore publié aucun événement : cette modification s'applique tout de suite.",
        'outro_immediate' => "Si vous n'êtes pas à l'origine de cette modification, corrigez le compte et vérifiez les accès de votre organisation.",
    ],

    'whatsapp' => [
        'alert' => 'Convive : le compte de versement ":label" de :tenant a changé. Ancien numéro : :before. Nouveau : :after. Si vous n\'êtes pas à l\'origine de cette demande, annulez-la immédiatement.',
    ],
];
