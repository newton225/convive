<?php

return [
    'title' => 'Comptes de versement',
    'description' => "Les comptes sur lesquels vos invités versent. Toute modification n'apparaît publiquement qu'après un délai de :hours heures.",

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
        'deleted' => 'Compte supprimé.',
    ],

    'errors' => [
        'account_number_required' => 'Ce canal exige un numéro de compte.',
        'number_invalid' => 'Saisissez un numéro ivoirien à 10 chiffres, par exemple 07 07 12 34 56.',
        'number_network' => 'Un numéro :channel commence par :prefixes. Vérifiez le numéro ou le canal choisi.',
    ],

    'confirm_approve' => [
        'title' => 'Activer ce changement maintenant ?',
        'description' => "Le nouveau numéro du compte \":label\" s'affichera tout de suite sur les liens publics, sans attendre la fin du délai de 24 heures. Vérifiez-le auprès de la personne qui l'a demandé, par un autre canal, avant de confirmer.",
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
    ],

    'whatsapp' => [
        'alert' => 'Convive : le compte de versement ":label" de :tenant a changé. Ancien numéro : :before. Nouveau : :after. Si vous n\'êtes pas à l\'origine de cette demande, annulez-la immédiatement.',
    ],
];
