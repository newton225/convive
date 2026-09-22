<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationType;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantAlert;
use Illuminate\Support\Facades\Notification;

/**
 * Envoie une alerte de l'application (README section 5), etape 10 de « Ordre de construction ».
 *
 * Deux destinations : l'equipe de l'organisation courante (`toTenantMembers`, ceux qui detiennent
 * la permission du type), ou une personne precise (`toUser`, pour une invitation d'equipe, qui
 * s'adresse a quelqu'un qui n'est pas encore membre).
 */
class SendAlert
{
    /**
     * Send the alert to the members of the current organisation who can act on it.
     *
     * Sans organisation active, rien n'est envoye : une alerte metier produite hors d'une
     * tenancy ne saurait pas a quelle equipe s'adresser, et refuser vaut mieux que deviner
     * (CLAUDE.md, « Tenancy non initialisee »).
     *
     * @param  array<string, mixed>  $params
     * @param  User|null  $except  L'acteur, qui n'a pas besoin d'etre prevenu de ce qu'il vient de faire.
     * @return int le nombre de membres prevenus
     */
    public function toTenantMembers(NotificationType $type, array $params, string $url, ?User $except = null): int
    {
        $tenant = Tenant::current();
        $permission = $type->recipientPermission();

        if ($tenant === null || $permission === null) {
            return 0;
        }

        $recipients = $tenant->membersWithPermission($permission, $except);

        if ($recipients->isEmpty()) {
            return 0;
        }

        Notification::send($recipients, new TenantAlert($type, $params, $url, $tenant->id, $tenant->name));

        return $recipients->count();
    }

    /**
     * Send the alert to one person.
     *
     * @param  array<string, mixed>  $params
     */
    public function toUser(User $user, NotificationType $type, array $params, string $url): void
    {
        $user->notify(new TenantAlert($type, $params, $url, Tenant::current()?->id, Tenant::current()?->name));
    }
}
