<?php

namespace App\Actions\Fortify;

use App\Actions\Tenants\CreateTenant;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\LegalDocument;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateTenant $createTenant)
    {
        //
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // Nom de l'organisation et telephone a l'inscription (prototype Convive.dc.html, decision du
        // 2026-09-27). Le nom reste facultatif pour une personne invitee, qui rejoint une
        // organisation existante ; le telephone, facultatif dans le profil, est demande ici pour
        // les alertes WhatsApp.
        // Le telephone sous sa forme unique avant validation, comme celui d'un invite.
        if (is_string($input['phone'] ?? null) && ($normalized = PhoneNumber::normalize($input['phone']))) {
            $input['phone'] = $normalized;
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'phone' => $this->phoneRules(required: true),
            'organisation_name' => ['nullable', 'string', 'max:255'],
            'password' => $this->passwordRules(),
            // L'acceptation des conditions et de la politique de confidentialite, gardee avec sa
            // date et la version acceptee : c'est la preuve du contrat.
            'terms' => ['accepted'],
        ], ['terms.accepted' => __('account.register.terms_required')])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'password' => $input['password'],
                'terms_accepted_at' => now(),
                'terms_version' => LegalDocument::Version,
            ]);

            $organisationName = trim((string) ($input['organisation_name'] ?? ''));

            $this->createTenant->handle(
                $user,
                $organisationName !== '' ? $organisationName : __('tenants.personal_name', ['name' => $user->name]),
                isPersonal: true,
            );

            return $user;
        });
    }
}
