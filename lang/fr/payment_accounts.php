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
    ],

    'confirm_approve' => [
        'title' => 'Activer ce changement maintenant ?',
        'description' => "Le nouveau numéro du compte \":label\" s'affichera tout de suite sur les liens publics, sans attendre la fin du délai de 24 heures. Vérifiez-le auprès de la personne qui l'a demandé, par un autre canal, avant de confirmer.",
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
