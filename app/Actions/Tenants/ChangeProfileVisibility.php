<?php

namespace App\Actions\Tenants;

use App\Models\Profile;

/**
 * Masque un profil de base, ou le propose de nouveau, dans l'organisation dont la base est active.
 * Ceux qui le portent le gardent, avec ses droits : seuls les choix proposes changent
 * (`Profile::scopeAssignable()`).
 */
class ChangeProfileVisibility
{
    public function handle(Profile $profile, bool $hidden): void
    {
        if ($profile->isHidden() === $hidden) {
            return;
        }

        $profile->hidden_at = $hidden ? now() : null;
        $profile->save();

        activity()
            ->performedOn($profile)
            ->event('updated')
            ->withProperties(['name' => $profile->name])
            ->log($hidden ? 'profile.hidden' : 'profile.shown');
    }
}
