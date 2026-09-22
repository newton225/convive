<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Le client de test HTTP envoie un en-tete Accept-Language anglais par defaut.
     * Les scenarios qui ne portent pas sur la negociation le neutralisent avec une
     * langue que l'application ne sert pas.
     */
    private function getWithoutLanguagePreference(string $uri): TestResponse
    {
        return $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->get($uri);
    }

    public function test_le_francais_est_la_langue_par_defaut(): void
    {
        $this->getWithoutLanguagePreference('/')->assertOk();

        $this->assertSame('fr', App::getLocale());
    }

    public function test_un_parametre_d_url_change_la_langue(): void
    {
        $this->getWithoutLanguagePreference('/?lang=en')->assertOk();

        $this->assertSame('en', App::getLocale());
    }

    public function test_une_langue_non_supportee_est_ignoree(): void
    {
        $this->getWithoutLanguagePreference('/?lang=it')->assertOk();

        $this->assertSame('fr', App::getLocale());
        $this->assertNull(session('locale'));
    }

    public function test_la_langue_choisie_est_memorisee_pour_les_requetes_suivantes(): void
    {
        $this->getWithoutLanguagePreference('/?lang=en')->assertOk();

        $this->getWithoutLanguagePreference('/')->assertOk();

        $this->assertSame('en', App::getLocale());
    }

    public function test_un_visiteur_anglophone_est_servi_en_anglais(): void
    {
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')
            ->get('/')
            ->assertOk();

        $this->assertSame('en', App::getLocale());
    }

    public function test_l_en_tete_du_navigateur_ne_change_pas_la_langue_du_back_office(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en-GB,en;q=0.9')
            ->get('/')
            ->assertOk();

        $this->assertSame('fr', App::getLocale());
    }

    public function test_un_membre_connecte_peut_tout_de_meme_choisir_sa_langue(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/?lang=en')->assertOk();

        $this->assertSame('en', App::getLocale());
    }

    public function test_les_pages_recoivent_la_langue_et_les_traductions(): void
    {
        $this->getWithoutLanguagePreference('/')
            ->assertInertia(fn ($page) => $page
                ->where('locale', 'fr')
                ->where('supportedLocales', Locale::supported())
                ->where('translations.common.actions.save', 'Enregistrer')
            );
    }

    public function test_les_traductions_partagees_suivent_la_langue_choisie(): void
    {
        $this->getWithoutLanguagePreference('/?lang=en')
            ->assertInertia(fn ($page) => $page
                ->where('locale', 'en')
                ->where('translations.common.actions.save', 'Save')
            );
    }

    public function test_le_selecteur_de_langue_enregistre_le_choix(): void
    {
        $this->from('/')->put('/locale', ['locale' => 'en'])
            ->assertRedirect('/');

        $this->assertSame('en', session('locale'));

        $this->getWithoutLanguagePreference('/')->assertOk();

        $this->assertSame('en', App::getLocale());
    }

    public function test_le_selecteur_de_langue_refuse_une_langue_inconnue(): void
    {
        $this->from('/')->put('/locale', ['locale' => 'it'])
            ->assertSessionHasErrors('locale');

        $this->assertNull(session('locale'));
    }

    public function test_les_messages_de_validation_sont_traduits(): void
    {
        $this->getWithoutLanguagePreference('/')->assertOk();

        $this->post('/login', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email' => 'Le champ adresse e-mail est obligatoire.']);
    }
}
