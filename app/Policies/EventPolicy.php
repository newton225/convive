<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;

/**
 * `Event` vit dans la base du locataire (voir CLAUDE.md, « Multi-locataire ») : un evenement
 * resolu par liaison de route ne peut venir d'aucune autre organisation que celle dont la base
 * est active. La verification d'appartenance qu'imposait l'ancien `tenant_id` n'a plus de
 * situation a couvrir ; seule la permission reste a verifier.
 */
class EventPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::EventsView);
    }

    public function view(User $user, Event $event, Tenant $tenant): bool
    {
        return $this->viewAny($user, $tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::EventsCreate);
    }

    public function update(User $user, Event $event, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::EventsUpdate);
    }

    public function duplicate(User $user, Event $event, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::EventsDuplicate);
    }

    /**
     * Determine whether the user can hand out the public link of the event.
     *
     * Publier est un acte separe de la modification : c'est lui qui rend l'evenement joignable
     * par des invites et qui fige le sous-domaine de l'organisation.
     */
    public function publish(User $user, Event $event, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::EventsUpdate);
    }

    public function close(User $user, Event $event, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::EventsClose);
    }

    /**
     * Determine whether the user can announce (or withdraw) the event on the product site's
     * showcase.
     *
     * Permission dediee, distincte de `events.update` (CLAUDE.md, « Annonce sur le site
     * produit ») : annoncer publiquement une organisation est une decision de visibilite, pas
     * une simple modification de fiche.
     */
    public function announce(User $user, Event $event, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::EventsAnnounce);
    }

    /**
     * Determine whether the user can delete the event.
     *
     * Un evenement publie ne se supprime pas : des invites en detiennent l'adresse. Il se
     * cloture.
     */
    public function delete(User $user, Event $event, Tenant $tenant): bool
    {
        return ! $event->isPublished() && $this->update($user, $event, $tenant);
    }
}
