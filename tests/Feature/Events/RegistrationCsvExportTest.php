<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * L'export CSV de la base d'inscrits (README ecran 20) s'ouvre tel quel dans un Excel reglé en
 * francais. Bogue signale par le proprietaire du projet le 2026-10-03, capture a l'appui : accents
 * illisibles (« TÃ©lÃ©phone »), tout dans la premiere colonne, et le « + » des numeros perdu.
 */
class RegistrationCsvExportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create(['name' => 'Anniversaire de Isaac']));
    }

    private function csv(): string
    {
        $response = $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.export.csv', [$this->tenant, $this->event]))
            ->assertOk();

        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);

        return (string) file_get_contents($response->baseResponse->getFile()->getPathname());
    }

    public function test_le_fichier_annonce_son_encodage_pour_qu_excel_lise_les_accents(): void
    {
        $this->assertStringStartsWith("\u{FEFF}", $this->csv());
    }

    public function test_les_colonnes_sont_separees_par_des_points_virgules(): void
    {
        $header = strtok(substr($this->csv(), 3), "\n");

        $this->assertStringContainsString('"Téléphone";"Email";"Unité"', (string) $header);
    }

    public function test_le_telephone_garde_son_indicatif_et_reste_du_texte_pour_le_tableur(): void
    {
        $this->tenant->asCurrent(function () {
            Registration::factory()->confirmed()->create([
                'event_id' => $this->event->id,
                'name' => 'KANGA Antoine',
                'phone' => '+2250104050206',
            ]);
            Registration::factory()->confirmed()->create([
                'event_id' => $this->event->id,
                'name' => 'Marie Dupont',
                'phone' => '+33612345678',
            ]);
        });

        $csv = $this->csv();

        // Separe par des espaces, le numero ne passe pour un nombre ni ici ni dans Excel, qui
        // retirerait sinon le « + » et l'afficherait en notation scientifique.
        $this->assertStringContainsString('"KANGA Antoine";"+225 01 04 05 02 06"', $csv);
        $this->assertStringContainsString('"Marie Dupont";"+33 6 12 34 56 78"', $csv);
    }

    public function test_un_numero_enregistre_sous_une_forme_ancienne_est_rendu_tel_quel(): void
    {
        $this->assertSame('07 07 12', PhoneNumber::display('07 07 12'));
        $this->assertSame('', PhoneNumber::display(''));
    }
}
