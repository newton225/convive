<?php

namespace Tests\Feature;

use App\Actions\Console\ManageOrganisation;
use App\Actions\Events\PurgeDeletedEvents;
use App\Models\User;
use App\Support\LegalDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Les pages juridiques du site (politique de confidentialite, conditions d'utilisation, mentions
 * legales) : ouvertes a tous, dans les deux langues, completees par l'identite de l'editeur, et
 * acceptees a la creation d'un compte.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function documents(): array
    {
        return [
            'confidentialite' => ['legal.privacy', 'privacy'],
            'conditions' => ['legal.terms', 'terms'],
            'mentions legales' => ['legal.notice', 'notice'],
        ];
    }

    #[DataProvider('documents')]
    public function test_la_page_s_ouvre_pour_un_visiteur(string $route, string $document): void
    {
        $this->get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('legal')
                ->where('document.slug', $document)
                ->where('version', LegalDocument::Version)
                ->has('document.sections.0.title'),
            );
    }

    #[DataProvider('documents')]
    public function test_la_page_s_ouvre_aussi_pour_un_membre_connecte(string $route): void
    {
        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get(route($route))
            ->assertOk();
    }

    #[DataProvider('documents')]
    public function test_le_document_existe_en_anglais_avec_autant_de_sections(string $route, string $document): void
    {
        $french = LegalDocument::get($document);

        app()->setLocale('en');
        $english = LegalDocument::get($document);

        $this->assertNotSame($french['title'], $english['title']);
        $this->assertCount(count($french['sections']), $english['sections']);

        foreach ($french['sections'] as $index => $section) {
            $this->assertCount(count($section['paragraphs']), $english['sections'][$index]['paragraphs']);
            $this->assertCount(count($section['items']), $english['sections'][$index]['items']);
            $this->assertCount(count($section['after']), $english['sections'][$index]['after']);
        }
    }

    public function test_l_identite_de_l_editeur_remplit_les_documents(): void
    {
        config([
            'convive.legal.editor_name' => 'Convive SARL',
            'convive.legal.editor_address' => 'Cocody, Abidjan',
            'convive.legal.privacy_email' => 'donnees@convive.test',
        ]);

        $text = json_encode(LegalDocument::get('privacy'), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Convive SARL (Cocody, Abidjan)', (string) $text);
        $this->assertStringContainsString('donnees@convive.test', (string) $text);
        // L'adresse n'est pas lue comme le nom de l'editeur suivi d'un reste.
        $this->assertStringNotContainsString('Convive SARL_address', (string) $text);
    }

    public function test_une_identite_manquante_se_voit_au_lieu_de_disparaitre(): void
    {
        config(['convive.legal.editor_name' => null]);

        $text = json_encode(LegalDocument::get('notice'), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString(__('legal.placeholder'), (string) $text);
        $this->assertContains('editor_name', LegalDocument::missingIdentity());
        // Aucun jeton brut ne reste dans le texte publie.
        $this->assertDoesNotMatchRegularExpression('/:[a-z_]{4,}/', (string) $text);
    }

    public function test_la_politique_annonce_les_durees_que_l_application_applique(): void
    {
        $text = json_encode(LegalDocument::get('privacy'), JSON_UNESCAPED_UNICODE);

        // Ces durees sont celles du code : si l'une change, le texte change avec elle.
        $this->assertStringContainsString('30 jours', (string) $text);
        $this->assertStringContainsString('un an au plus', (string) $text);
        $this->assertStringContainsString('24 mois', (string) $text);
        $this->assertSame(30, ManageOrganisation::DeletionDelayDays);
        $this->assertSame(30, PurgeDeletedEvents::RetentionDays);
    }

    public function test_un_compte_ne_se_cree_pas_sans_accepter_les_conditions(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Amara Kone',
            'email' => 'amara@example.com',
            'phone' => '+225 07 07 12 34 56',
            'password' => 'Convive-2026!',
            'password_confirmation' => 'Convive-2026!',
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
        $this->assertNull(User::where('email', 'amara@example.com')->first());
    }

    public function test_l_acceptation_est_gardee_avec_sa_date_et_la_version_acceptee(): void
    {
        $this->freezeSecond();

        $this->post(route('register.store'), [
            'name' => 'Amara Kone',
            'email' => 'amara@example.com',
            'phone' => '+225 07 07 12 34 56',
            'password' => 'Convive-2026!',
            'password_confirmation' => 'Convive-2026!',
            'terms' => 'on',
        ]);

        $user = User::where('email', 'amara@example.com')->firstOrFail();

        $this->assertTrue($user->terms_accepted_at->equalTo(now()));
        $this->assertSame(LegalDocument::Version, $user->terms_version);
    }
}
