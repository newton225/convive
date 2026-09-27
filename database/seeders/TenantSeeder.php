<?php

namespace Database\Seeders;

use App\Actions\Tenants\CreateTenant;
use App\Enums\BillingCurrency;
use App\Enums\LegalForm;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * L'organisation de demonstration, avec son identite legale et sa marque completes, de sorte
 * que l'ecran « Espace et marque » ait quelque chose a montrer des la premiere ouverture.
 */
class TenantSeeder extends Seeder
{
    public const AdminEmail = 'admin@convive.com';

    public const TenantName = 'Association Convive';

    public function run(): void
    {
        $admin = $this->admin();
        $tenant = $this->tenant($admin);

        $tenant->brandingOrCreate()->fill([
            'display_name' => 'Convive',
            'legal_name' => 'Association Convive Cote d Ivoire',
            'legal_form' => LegalForm::Association,
            'representative_name' => 'Aya Kouassi',
            'registration_number' => 'CI-ABJ-2021-B-04812',
            'tax_number' => '1804517 K',
            'address' => 'Rue des Jardins, Cocody Deux Plateaux',
            'city' => 'Abidjan',
            'country' => 'CI',
            'email' => 'contact@convive.ci',
            'phone' => '+225 07 07 12 34 56',
            'primary_color' => '#7b1e3a',
            'secondary_color' => '#c9a227',
        ])->save();

        if ($tenant->subdomain === null) {
            $tenant->update(['subdomain' => 'convive']);
        }

        // Plan Association : l'organisation de demonstration porte plusieurs evenements actifs,
        // au-dela du plafond d'Essentiel (1). Abonnement actif fictif, sans client Stripe.
        Subscription::firstOrCreate(['tenant_id' => $tenant->id], [
            'plan_id' => Plan::ensure(PlanCode::Association)->id,
            'status' => SubscriptionStatus::Active,
            'currency' => BillingCurrency::Xof->value,
            'current_period_ends_at' => now()->addMonth(),
        ]);

        $admin->switchTenant($tenant);
    }

    /**
     * Get the development account described in CLAUDE.md, creating it when absent.
     *
     * La fabrique lui donne au passage son organisation personnelle : un utilisateur sans
     * espace personnel est un etat que l'application ne produit jamais.
     */
    private function admin(): User
    {
        $admin = User::where('email', self::AdminEmail)->first();

        if ($admin) {
            return $admin;
        }

        return User::factory()->create([
            'name' => 'Isaac N Dri',
            'email' => self::AdminEmail,
            'password' => Hash::make('password'),
        ]);
    }

    private function tenant(User $admin): Tenant
    {
        $tenant = Tenant::where('name', self::TenantName)->first();

        return $tenant ?? app(CreateTenant::class)->handle($admin, self::TenantName);
    }
}
