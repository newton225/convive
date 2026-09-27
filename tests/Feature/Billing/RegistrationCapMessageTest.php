<?php

namespace Tests\Feature\Billing;

use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une organisation au plafond d'inscrits de son plan (README section 3) refuse les reservations :
 * l'invite, qui voit encore des places sur la page de l'evenement, recoit un message qui explique
 * que les inscriptions en ligne sont suspendues, au lieu d'un retour muet sur l'evenement.
 */
class RegistrationCapMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_invite_est_prevenu_quand_le_plafond_du_plan_est_atteint(): void
    {
        config(['convive.billing.enforce_plan_limits' => true]);

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $tenant->brandingOrCreate()->fill([
            'display_name' => 'Convive',
            'legal_name' => 'Association Convive',
            'legal_form' => LegalForm::Association,
            'representative_name' => 'Aya Kouassi',
            'registration_number' => 'CI-ABJ-2021-B-04812',
            'tax_number' => '1804517 K',
            'address' => 'Rue des Jardins',
            'city' => 'Abidjan',
            'country' => 'CI',
            'phone' => '+225 07 07 12 34 56',
        ])->save();
        $tenant->update(['subdomain' => 'convive-ci']);
        $tenant = $tenant->fresh();

        $event = $tenant->asCurrent(function () {
            PaymentAccount::factory()->create();
            $event = Event::factory()->published()->create(['table_count' => 50, 'seats_per_table' => 10]);
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());

            // Plafond du plan Essentiel atteint par un autre evenement de l'organisation.
            $other = Event::factory()->open()->create();
            Registration::factory()->confirmed()->create(['event_id' => $other->id, 'party_size' => 200]);

            return $event->fresh();
        });

        $appUrl = parse_url((string) config('app.url'));
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';
        $url = ($appUrl['scheme'] ?? 'http').'://convive-ci.'.config('convive.public_domain').$port
            .'/e/'.$event->public_token.'/register';

        $this->post($url, [
            'name' => 'Aya Kouassi',
            'phone' => '+225 05 05 11 22 33',
            'unit_id' => $tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id')),
            'companions' => [],
        ])->assertSessionHasErrors(['registration' => __('guest.registration.errors.registrations_paused')]);

        $this->assertSame(0, $tenant->asCurrent(fn () => Registration::where('event_id', $event->id)->count()));
    }
}
