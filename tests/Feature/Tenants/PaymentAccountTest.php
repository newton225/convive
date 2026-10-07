<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\PaymentChannel;
use App\Enums\TenantPermission;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Models\PaymentAccount;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Tenants\PaymentAccountChanged;
use App\Support\GettingStarted;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PaymentAccountTest extends TestCase
{
    use RefreshDatabase;

    protected bool $confirmsPasswordOnActingAs = false;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // La tache d'activation tourne toutes les cinq minutes : on cale l'horloge sur une
        // minute ou elle est due, sinon `schedule:run` ne declenche rien et les tests
        // mesureraient le planificateur au lieu de la regle.
        $this->travelTo(now()->startOfHour());
    }

    /**
     * Une organisation qui a deja publie : le delai d'activation s'applique (avant la premiere
     * publication, voir `PaymentAccountFirstPublicationTest`).
     */
    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        $tenant = app(CreateTenant::class)->handle($user, $name);
        $tenant->forceFill(['first_published_at' => now()])->save();

        return $tenant;
    }

    /**
     * La re-authentification est exigee sur toutes ces routes : en test, on la considere
     * fraiche, exactement comme un membre qui vient de saisir son mot de passe et de rejouer
     * son code a deux facteurs (SECURITY.md C1). `$user` doit porter la 2FA
     * (`User::factory()->withTwoFactor()`), sinon `EnsureRecentTwoFactorConfirmation` renverrait
     * vers l'ecran de securite plutot que d'accepter cette confirmation.
     */
    private function actingAsConfirmed(User $user): static
    {
        $this->actingAs($user);
        // `now()` et non `time()` : apres un voyage dans le temps, la confirmation datee
        // de l'horloge reelle serait consideree comme perimee.
        //
        // Une session fraiche, aussi : ces tests sautent 25 heures entre deux requetes, et une
        // session ouverte avant le saut depasse sa duree de vie absolue (12 h,
        // `EnforceAbsoluteSessionLifetime`). Le lendemain, le membre s'est reconnecte.
        $this->session([
            'auth.password_confirmed_at' => now()->getTimestamp(),
            'auth.two_factor_confirmed_at' => now()->getTimestamp(),
            EnforceAbsoluteSessionLifetime::SessionKey => now()->getTimestamp(),
        ]);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Wave principal',
            'channel' => PaymentChannel::Wave->value,
            'account_number' => '+225 07 00 00 00 01',
            'holder_name' => 'Association Convive',
            'instructions' => 'Mettez votre nom en motif du transfert.',
            'is_active' => true,
        ], $overrides);
    }

    private function createAccount(Tenant $tenant, User $owner): PaymentAccount
    {
        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.store', $tenant), $this->payload());

        return $tenant->asCurrent(fn () => PaymentAccount::firstOrFail());
    }

    private function requestChange(Tenant $tenant, User $actor, PaymentAccount $account, string $number): TestResponse
    {
        return $this->actingAsConfirmed($actor)->patch(
            route('tenants.payment-accounts.update', [$tenant, $account]),
            $this->payload(['account_number' => $number]),
        );
    }

    /**
     * Read the latest state of a payment account. `PaymentAccount` vit dans la base du
     * locataire : une fois la requete HTTP terminee, la tenancy n'est plus active
     * (EnsureTenantMembership la termine), donc `->fresh()` seul echouerait.
     */
    private function fresh(Tenant $tenant, PaymentAccount $account): PaymentAccount
    {
        return $tenant->asCurrent(fn () => $account->fresh());
    }

    public function test_un_compte_nouvellement_cree_n_est_pas_visible_publiquement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $account = $this->createAccount($tenant, $owner);

        // Sans cette regle, il suffirait d'ajouter un compte plutot que d'en modifier un pour
        // contourner le delai d'activation.
        $this->assertFalse($account->isPubliclyVisible());
        $this->assertNull($account->account_number);
        $this->assertTrue($account->hasPendingChange());
    }

    public function test_le_delai_d_activation_est_de_vingt_quatre_heures(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $account = $this->createAccount($tenant, $owner);

        $this->assertEqualsWithDelta(
            24 * 60,
            now()->diffInMinutes($account->pending_activates_at),
            1,
        );
    }

    public function test_l_ancien_numero_reste_affiche_pendant_le_delai(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        $this->travel(25)->hours();
        $this->artisan('schedule:run')->assertSuccessful();

        $account = $this->fresh($tenant, $account);
        $this->requestChange($tenant, $owner, $account, '+225 07 99 99 99 99');

        $account = $this->fresh($tenant, $account);

        $this->assertSame('+225 07 00 00 00 01', $account->account_number);
        $this->assertSame('+225 07 99 99 99 99', $account->pending_account_number);
        $this->assertTrue($account->isPubliclyVisible());
    }

    public function test_le_changement_s_applique_une_fois_le_delai_ecoule(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        $this->travel(25)->hours();
        $this->artisan('schedule:run')->assertSuccessful();

        $account = $this->fresh($tenant, $account);

        $this->assertSame('+225 07 00 00 00 01', $account->account_number);
        $this->assertFalse($account->hasPendingChange());
        $this->assertTrue($account->isPubliclyVisible());
    }

    public function test_le_changement_ne_s_applique_pas_avant_le_delai(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        $this->travel(23)->hours();
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertNull($this->fresh($tenant, $account)->account_number);
    }

    public function test_un_second_proprietaire_leve_le_delai(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        $second = User::factory()->withTwoFactor()->create();
        $tenant->addMember($second, $tenant->run(fn () => Profile::where('name', Profile::Owner)->firstOrFail()));

        $this->actingAsConfirmed($second)
            ->post(route('tenants.payment-accounts.approve', [$tenant, $account]))
            ->assertRedirect();

        $account = $this->fresh($tenant, $account);

        $this->assertSame('+225 07 00 00 00 01', $account->account_number);
        $this->assertFalse($account->hasPendingChange());
    }

    public function test_le_demandeur_ne_peut_pas_valider_sa_propre_modification(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        // Sans cette regle, un compte compromis se validerait lui-meme et le delai ne
        // protegerait plus de rien.
        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.approve', [$tenant, $account]))
            ->assertForbidden();

        $this->assertNull($this->fresh($tenant, $account)->account_number);
    }

    public function test_un_non_proprietaire_ne_peut_pas_lever_le_delai(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        $treasurer = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $treasurer, [TenantPermission::TenantPaymentAccounts]);

        $this->actingAsConfirmed($treasurer)
            ->post(route('tenants.payment-accounts.approve', [$tenant, $account]))
            ->assertForbidden();
    }

    public function test_une_modification_en_attente_peut_etre_annulee_et_l_ancien_numero_reste(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        $this->travel(25)->hours();
        $this->artisan('schedule:run');

        $this->requestChange($tenant, $owner, $account, '+225 07 00 00 00 99');

        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.cancel', [$tenant, $account]))
            ->assertRedirect();

        $account = $this->fresh($tenant, $account);

        $this->assertFalse($account->hasPendingChange());
        $this->assertSame('+225 07 00 00 00 01', $account->account_number);
    }

    public function test_annuler_la_demande_d_un_compte_jamais_actif_annule_sa_creation(): void
    {
        // Sans cela, le compte restait une coquille vide : ni canal, ni numero, jamais visible
        // (constate le 2026-10-04).
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.cancel', [$tenant, $account]))
            ->assertRedirect(route('tenants.payment-accounts.index', $tenant));

        $this->assertSame(0, $tenant->asCurrent(fn () => PaymentAccount::count()));
        $this->assertTrue($tenant->asCurrent(fn () => Activity::where('description', 'payment_account.creation_cancelled')->exists()));
    }

    public function test_la_page_distingue_un_compte_jamais_actif_et_montre_toute_la_demande(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $this->createAccount($tenant, $owner);

        $this->actingAsConfirmed($owner)
            ->get(route('tenants.payment-accounts.index', $tenant))
            ->assertInertia(fn ($page) => $page
                ->where('accounts.0.neverActive', true)
                ->where('accounts.0.pending.channelLabel', PaymentChannel::Wave->label())
                ->where('accounts.0.pending.holderName', 'Association Convive'));
    }

    public function test_le_libelle_et_la_consigne_prennent_effet_immediatement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        $this->travel(25)->hours();
        $this->artisan('schedule:run')->assertSuccessful();

        // Le libelle ne designe pas ou va l'argent : rien ne justifie de le faire attendre.
        $this->actingAsConfirmed($owner)->patch(
            route('tenants.payment-accounts.update', [$tenant, $account]),
            $this->payload(['label' => 'Wave secondaire']),
        );

        $account = $this->fresh($tenant, $account);

        $this->assertSame('Wave secondaire', $account->label);
        $this->assertFalse($account->hasPendingChange());
    }

    public function test_les_porteurs_de_la_permission_sont_prevenus(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $treasurer = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $treasurer, [TenantPermission::TenantPaymentAccounts]);

        $reader = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $reader, [TenantPermission::EventsView], 'Lecteur');

        $this->createAccount($tenant, $owner);

        Notification::assertSentTo([$owner, $treasurer], PaymentAccountChanged::class);
        Notification::assertNotSentTo($reader, PaymentAccountChanged::class);
    }

    public function test_l_alerte_porte_l_ancien_et_le_nouveau_numero(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        $this->travel(25)->hours();
        $this->artisan('schedule:run')->assertSuccessful();

        Notification::fake();

        $this->requestChange($tenant, $owner, $this->fresh($tenant, $account), '+225 07 99 99 99 99');

        Notification::assertSentTo($owner, PaymentAccountChanged::class, function (PaymentAccountChanged $notification) {
            return $notification->before['account_number'] === '+225 07 00 00 00 01'
                && $notification->account->pending_account_number === '+225 07 99 99 99 99';
        });
    }

    public function test_l_alerte_est_aussi_tentee_par_whatsapp(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->createAccount($tenant, $owner);

        // Toujours tentee : `routeNotificationForWhatsapp()` saute simplement l'envoi sans
        // numero, comme `mail` le fait deja pour une inscription sans email.
        Notification::assertSentTo($owner, PaymentAccountChanged::class, function (PaymentAccountChanged $notification) use ($owner) {
            return in_array('whatsapp', $notification->via($owner), true)
                && str_contains($notification->toWhatsApp($owner), $notification->account->label);
        });
    }

    public function test_un_membre_sans_telephone_ne_route_pas_l_alerte_whatsapp(): void
    {
        $owner = User::factory()->withTwoFactor()->create();

        $this->assertNull($owner->routeNotificationForWhatsapp());

        $owner->phone = '+225 07 00 00 00 01';

        $this->assertSame('+225 07 00 00 00 01', $owner->routeNotificationForWhatsapp());
    }

    public function test_un_changement_reste_signale_pendant_sept_jours(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        $this->travel(25)->hours();
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertTrue($this->fresh($tenant, $account)->changedRecently());

        $this->travel(8)->days();

        $this->assertFalse($this->fresh($tenant, $account)->changedRecently());
    }

    public function test_sans_la_permission_les_comptes_ne_sont_pas_accessibles(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::TenantLegal]);

        $this->actingAsConfirmed($member)
            ->get(route('tenants.payment-accounts.index', $tenant))
            ->assertForbidden();

        $this->actingAsConfirmed($member)
            ->post(route('tenants.payment-accounts.store', $tenant), $this->payload())
            ->assertForbidden();
    }

    public function test_la_re_authentification_est_exigee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        // Session authentifiee mais mot de passe non reconfirme : la porte reste fermee.
        $this->actingAs($owner)
            ->get(route('tenants.payment-accounts.index', $tenant))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_le_rejeu_du_second_facteur_est_exige_sur_une_modification(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        // Mot de passe reconfirme, mais aucun code a deux facteurs rejoue : la lecture passe,
        // la modification non (CLAUDE.md, « juste avant la modification »).
        $this->actingAs($owner);
        $this->session(['auth.password_confirmed_at' => now()->getTimestamp()]);

        $this->get(route('tenants.payment-accounts.index', $tenant))->assertOk();

        $this->post(route('tenants.payment-accounts.store', $tenant), $this->payload())
            ->assertRedirect(route('two-factor.reconfirm.show'));
    }

    public function test_un_code_a_deux_facteurs_valide_debloque_la_modification(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner);
        $this->session(['auth.password_confirmed_at' => now()->getTimestamp()]);

        $code = app(Google2FA::class)->getCurrentOtp(UserFactory::TwoFactorSecret);

        $this->post(route('two-factor.reconfirm.store'), ['code' => $code])
            ->assertRedirect();

        $this->post(route('tenants.payment-accounts.store', $tenant), $this->payload())
            ->assertRedirect(route('tenants.payment-accounts.index', $tenant));
    }

    public function test_un_code_a_deux_facteurs_invalide_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();

        $this->actingAs($owner);

        $this->post(route('two-factor.reconfirm.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertNull(session('auth.two_factor_confirmed_at'));
    }

    public function test_un_membre_sans_2fa_est_renvoye_l_activer(): void
    {
        // Un profil personnalise, qui n'exige pas la 2FA lui-meme (a l'inverse de Proprietaire
        // et Tresorier), pour isoler ce garde de celui, deja teste ailleurs, d'
        // `EnsureTwoFactorForProfile`. Sans double authentification, aucun code n'existe a
        // rejouer : le renvoyer vers l'ecran de securite plutot que de le bloquer sans
        // explication.
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $member = User::factory()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::TenantPaymentAccounts]);

        $this->actingAs($member);
        $this->session(['auth.password_confirmed_at' => now()->getTimestamp()]);

        $this->post(route('tenants.payment-accounts.store', $tenant), $this->payload())
            ->assertRedirect(route('security.edit'));
    }

    public function test_un_locataire_tiers_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAsConfirmed(User::factory()->withTwoFactor()->create())
            ->get(route('tenants.payment-accounts.index', $tenant))
            ->assertNotFound();
    }

    public function test_un_compte_d_un_autre_locataire_ne_peut_pas_etre_modifie(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $other = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Autre');
        $foreign = $this->createAccount($other, $other->owner());

        $this->requestChange($tenant, $owner, $foreign, '+225 07 99 99 99 99')
            ->assertNotFound();

        $this->assertSame('+225 07 00 00 00 01', $this->fresh($other, $foreign)->pending_account_number);
    }

    public function test_un_canal_exigeant_un_numero_le_reclame(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.store', $tenant), $this->payload([
                'account_number' => '',
            ]))
            ->assertSessionHasErrors('account_number');
    }

    public function test_les_especes_n_exigent_pas_de_numero(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.store', $tenant), $this->payload([
                'label' => 'A la porte',
                'channel' => PaymentChannel::Cash->value,
                'account_number' => '',
            ]))
            ->assertRedirect();
    }

    public function test_un_canal_hors_catalogue_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.store', $tenant), $this->payload([
                'channel' => 'bitcoin',
            ]))
            ->assertSessionHasErrors('channel');
    }

    public function test_la_demande_de_changement_est_journalisee_avec_l_avant_et_l_apres(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        $this->travel(25)->hours();
        $this->artisan('schedule:run')->assertSuccessful();

        $this->requestChange($tenant, $owner, $this->fresh($tenant, $account), '+225 07 99 99 99 99');

        $activity = $tenant->asCurrent(fn () => Activity::where('description', 'payment_account.change_requested')
            ->latest('id')
            ->first());

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame('+225 07 00 00 00 01', $activity->properties['old']['account_number']);
        $this->assertSame('+225 07 99 99 99 99', $activity->properties['attributes']['account_number']);
    }

    public function test_le_tableau_de_bord_signale_un_changement_recent(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $this->createAccount($tenant, $owner);

        $this->travel(25)->hours();
        $this->artisan('schedule:run')->assertSuccessful();

        $this->actingAsConfirmed($owner)
            ->get(route('dashboard', ['current_tenant' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('paymentAccountNotice.changed', true));
    }

    public function test_le_tableau_de_bord_ne_signale_rien_sans_la_permission(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $this->createAccount($tenant, $owner);

        $this->travel(25)->hours();
        $this->artisan('schedule:run')->assertSuccessful();

        $reader = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $reader, [TenantPermission::EventsView], 'Lecteur');

        $this->actingAsConfirmed($reader)
            ->get(route('dashboard', ['current_tenant' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('paymentAccountNotice', null));
    }

    public function test_le_signalement_disparait_au_bout_de_sept_jours(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $this->createAccount($tenant, $owner);

        $this->travel(25)->hours();
        $this->artisan('schedule:run')->assertSuccessful();

        $this->travel(8)->days();

        $this->actingAsConfirmed($owner)
            ->get(route('dashboard', ['current_tenant' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('paymentAccountNotice', null));
    }

    /**
     * Numero demande pour un nouveau compte : il n'a pas encore de valeur vivante, la demande
     * porte dans `pending_account_number`.
     */
    private function requestedNumber(Tenant $tenant): ?string
    {
        return $tenant->asCurrent(fn () => PaymentAccount::latest('id')->value('pending_account_number'));
    }

    public function test_un_numero_orange_doit_commencer_par_07(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.store', $tenant), $this->payload([
                'channel' => PaymentChannel::OrangeMoney->value,
                'account_number' => '+225 05 05 11 22 33',
            ]))
            ->assertSessionHasErrors('account_number');

        $this->assertSame(0, $tenant->asCurrent(fn () => PaymentAccount::count()));
    }

    public function test_wave_accepte_un_numero_de_tout_reseau_mobile(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.store', $tenant), $this->payload([
                'channel' => PaymentChannel::Wave->value,
                'account_number' => '05 05 11 22 33',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('+225 05 05 11 22 33', $this->requestedNumber($tenant));
    }

    public function test_le_numero_est_enregistre_sous_une_forme_unique(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.store', $tenant), $this->payload([
                'channel' => PaymentChannel::MoovMoney->value,
                'account_number' => '00225 0101020304',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('+225 01 01 02 03 04', $this->requestedNumber($tenant));
    }

    public function test_un_numero_d_un_autre_pays_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.store', $tenant), $this->payload([
                'channel' => PaymentChannel::Wave->value,
                'account_number' => '+221 77 123 45 67',
            ]))
            ->assertSessionHasErrors('account_number');
    }

    public function test_un_virement_garde_son_numero_de_compte_tel_quel(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        // Un RIB n'est pas un telephone : aucun format de reseau mobile ne s'y applique.
        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.store', $tenant), $this->payload([
                'channel' => PaymentChannel::BankTransfer->value,
                'account_number' => 'CI008 01234 012345678901 23',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('CI008 01234 012345678901 23', $this->requestedNumber($tenant));
    }

    public function test_un_compte_cree_depuis_les_premiers_pas_ramene_au_tableau_de_bord(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAsConfirmed($owner)
            ->post(route('tenants.payment-accounts.store', [$tenant, ...GettingStarted::ReturnQuery]), $this->payload())
            ->assertRedirect(route('dashboard', $tenant));
    }

    public function test_la_page_fournit_les_valeurs_en_attente_pour_preremplir_le_formulaire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $this->createAccount($tenant, $owner);

        $this->actingAsConfirmed($owner)
            ->get(route('tenants.payment-accounts.index', $tenant))
            ->assertInertia(fn ($page) => $page
                ->where('accounts.0.pending.channel', PaymentChannel::Wave->value)
                ->where('accounts.0.pending.accountNumber', '+225 07 00 00 00 01')
                ->where('accounts.0.pending.holderName', 'Association Convive'));
    }

    public function test_renvoyer_la_demande_en_attente_ne_relance_pas_le_delai(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);
        $activatesAt = $account->pending_activates_at;

        // Le formulaire reprend la demande en attente : seul le libelle change, le delai court
        // toujours depuis la demande d'origine.
        $this->travel(2)->hours();

        $this->actingAsConfirmed($owner)
            ->patch(route('tenants.payment-accounts.update', [$tenant, $account]), $this->payload(['label' => 'Wave du bureau']))
            ->assertRedirect();

        $account = $this->fresh($tenant, $account);

        $this->assertSame('Wave du bureau', $account->label);
        $this->assertTrue($account->hasPendingChange());
        $this->assertSame($activatesAt->toIso8601String(), $account->pending_activates_at->toIso8601String());
    }

    public function test_revenir_aux_valeurs_actives_annule_la_demande_en_attente(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $account = $this->createAccount($tenant, $owner);

        $this->travel(25)->hours();
        $this->artisan('schedule:run')->assertSuccessful();

        $account = $this->fresh($tenant, $account);
        $this->requestChange($tenant, $owner, $account, '+225 07 99 99 99 99');

        // Le demandeur relit sa demande et remet l'ancien numero : il n'y a plus rien a activer.
        $this->requestChange($tenant, $owner, $this->fresh($tenant, $account), '+225 07 00 00 00 01');

        $account = $this->fresh($tenant, $account);

        $this->assertFalse($account->hasPendingChange());
        $this->assertSame('+225 07 00 00 00 01', $account->account_number);
    }
}
