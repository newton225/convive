<?php

namespace App\Actions\Console;

use App\Enums\ConsoleProfile;
use App\Models\ConsoleOperator;
use App\Models\User;
use App\Notifications\Console\ConsoleOperatorInvited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * L'equipe editeur (README ecran 34) : inviter une personne par son adresse, la retirer. Chaque
 * geste est trace dans le journal applicatif en attendant le journal central de la console
 * (ecran 33), encore sur jeu d'exemple.
 */
class ManageConsoleTeam
{
    /**
     * Add the given address to the team with the given profile, and tell its owner by email.
     */
    public function invite(string $email, ConsoleProfile $profile, User $invitedBy): ConsoleOperator
    {
        $operator = ConsoleOperator::create([
            'email' => strtolower($email),
            'profile' => $profile,
            'invited_by_id' => $invitedBy->id,
        ]);

        Notification::route('mail', $operator->email)
            ->notify(new ConsoleOperatorInvited($operator, $invitedBy->name));

        Log::info('console.operator_invited', [
            'email' => $operator->email,
            'profile' => $profile->value,
            'actor_id' => $invitedBy->id,
        ]);

        return $operator;
    }

    /**
     * Remove a member from the team : their account opens nothing of the console from the next
     * request on. Personne ne se retire soi-meme : un Fondateur distrait ne doit pas pouvoir
     * laisser la console sans personne pour la tenir.
     *
     * @throws ValidationException
     */
    public function remove(ConsoleOperator $operator, User $removedBy): void
    {
        if (strtolower($removedBy->email) === $operator->email) {
            throw ValidationException::withMessages([
                'operator' => __('console.team.errors.cannot_remove_self'),
            ]);
        }

        $operator->delete();

        Log::info('console.operator_removed', [
            'email' => $operator->email,
            'profile' => $operator->profile->value,
            'actor_id' => $removedBy->id,
        ]);
    }
}
