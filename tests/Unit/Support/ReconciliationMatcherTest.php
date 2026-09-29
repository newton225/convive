<?php

namespace Tests\Unit\Support;

use App\Enums\ReconciliationOutcome;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\StatementLine;
use App\Support\ReconciliationMatcher;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * L'algorithme de rapprochement du releve (README 2.10), etape 9 de « Ordre de construction ».
 *
 * Fonction pure sur des modeles construits en memoire : aucune base n'est necessaire. Le
 * matcher ne regarde que les preuves candidates recues, jamais la base ; exclure celles deja
 * consommees dans le meme import est le role de l'Action appelante.
 */
class ReconciliationMatcherTest extends TestCase
{
    private function line(?string $reference, string $issuer, int $amount): StatementLine
    {
        return new StatementLine([
            'reference' => $reference,
            'issuer' => $issuer,
            'amount' => $amount,
        ]);
    }

    /**
     * Le montant compare a la ligne du releve est le montant du de l'inscription : l'invite ne
     * declare plus de montant (decision du proprietaire du projet, 2026-09-29).
     */
    private function proof(int $id, ?string $reference, int $amountDue, string $registrantName): PaymentProof
    {
        $proof = new PaymentProof(['reference' => $reference]);
        $proof->forceFill(['id' => $id]);
        $proof->setRelation('registration', new Registration(['name' => $registrantName, 'amount_due' => $amountDue]));

        return $proof;
    }

    /**
     * @param  array<int, PaymentProof>  $proofs
     * @return Collection<int, PaymentProof>
     */
    private function candidates(array $proofs): Collection
    {
        return collect($proofs);
    }

    public function test_une_reference_identique_et_un_montant_egal_donnent_rapprochee(): void
    {
        $proof = $this->proof(1, 'WV123456', 15000, 'Aya Kouassi');

        $result = ReconciliationMatcher::match($this->line('WV123456', 'Aya Kouassi', 15000), $this->candidates([$proof]));

        $this->assertSame(ReconciliationOutcome::Matched, $result['outcome']);
        $this->assertSame(1, $result['proof']?->id);
    }

    public function test_la_reference_se_compare_sans_casse_ni_espaces_de_bord(): void
    {
        $proof = $this->proof(1, 'WV123456', 15000, 'Aya Kouassi');

        $result = ReconciliationMatcher::match($this->line('  wv123456 ', 'Inconnu', 15000), $this->candidates([$proof]));

        $this->assertSame(ReconciliationOutcome::Matched, $result['outcome']);
    }

    public function test_une_reference_identique_et_un_montant_different_donnent_montant_divergent(): void
    {
        $proof = $this->proof(1, 'WV123456', 15000, 'Aya Kouassi');

        $result = ReconciliationMatcher::match($this->line('WV123456', 'Aya Kouassi', 10000), $this->candidates([$proof]));

        $this->assertSame(ReconciliationOutcome::AmountMismatch, $result['outcome']);
        $this->assertSame(1, $result['proof']?->id);
    }

    public function test_une_reference_portee_par_plusieurs_preuves_est_laissee_a_la_resolution_manuelle(): void
    {
        $candidates = $this->candidates([
            $this->proof(1, 'WV123456', 15000, 'Aya Kouassi'),
            $this->proof(2, 'WV123456', 15000, 'Kofi Diallo'),
        ]);

        $result = ReconciliationMatcher::match($this->line('WV123456', 'Aya Kouassi', 15000), $candidates);

        $this->assertSame(ReconciliationOutcome::NoRegistration, $result['outcome']);
        $this->assertNull($result['proof']);
    }

    public function test_sans_reference_connue_un_montant_egal_et_un_nom_proche_donnent_nom_approchant(): void
    {
        $proof = $this->proof(1, 'WV123456', 15000, 'Aya Kouassi');

        $result = ReconciliationMatcher::match($this->line('AUTRE999', 'Aya Kouasi', 15000), $this->candidates([$proof]));

        $this->assertSame(ReconciliationOutcome::ApproximateName, $result['outcome']);
        $this->assertSame(1, $result['proof']?->id);
    }

    public function test_une_ligne_sans_reference_se_rapproche_par_montant_et_nom(): void
    {
        $proof = $this->proof(1, 'WV123456', 15000, 'Aya Kouassi');

        $result = ReconciliationMatcher::match($this->line(null, 'Aya Kouasi', 15000), $this->candidates([$proof]));

        $this->assertSame(ReconciliationOutcome::ApproximateName, $result['outcome']);
    }

    public function test_l_ordre_des_mots_du_nom_est_indifferent(): void
    {
        $proof = $this->proof(1, 'WV123456', 15000, 'Aya Kouassi');

        // Les releves Mobile Money listent souvent le nom de famille en premier.
        $result = ReconciliationMatcher::match($this->line(null, 'KOUASSI Aya', 15000), $this->candidates([$proof]));

        $this->assertSame(ReconciliationOutcome::ApproximateName, $result['outcome']);
    }

    public function test_la_casse_et_les_accents_du_nom_sont_indifferents(): void
    {
        $proof = $this->proof(1, 'WV123456', 15000, 'Aissatou Kone');

        $result = ReconciliationMatcher::match($this->line(null, 'AÏSSATOU KONÉ', 15000), $this->candidates([$proof]));

        $this->assertSame(ReconciliationOutcome::ApproximateName, $result['outcome']);
    }

    public function test_un_nom_trop_different_ne_se_rapproche_pas(): void
    {
        $proof = $this->proof(1, 'WV123456', 15000, 'Aya Kouassi');

        $result = ReconciliationMatcher::match($this->line(null, 'Marcel Ouedraogo', 15000), $this->candidates([$proof]));

        $this->assertSame(ReconciliationOutcome::NoRegistration, $result['outcome']);
        $this->assertNull($result['proof']);
    }

    public function test_un_nom_proche_ne_suffit_pas_si_le_montant_differe(): void
    {
        $proof = $this->proof(1, 'WV123456', 15000, 'Aya Kouassi');

        $result = ReconciliationMatcher::match($this->line(null, 'Aya Kouassi', 12000), $this->candidates([$proof]));

        $this->assertSame(ReconciliationOutcome::NoRegistration, $result['outcome']);
    }

    public function test_le_seuil_de_similarite_est_de_soixante_dix_pour_cent(): void
    {
        $this->assertSame(70, ReconciliationMatcher::NameSimilarityThreshold);
    }

    public function test_entre_deux_noms_approchants_le_plus_proche_l_emporte(): void
    {
        $candidates = $this->candidates([
            $this->proof(1, 'A1', 15000, 'Aya Kouassi Junior'),
            $this->proof(2, 'A2', 15000, 'Aya Kouassi'),
        ]);

        $result = ReconciliationMatcher::match($this->line(null, 'Aya Kouassi', 15000), $candidates);

        $this->assertSame(2, $result['proof']?->id);
    }

    public function test_sans_preuve_candidate_la_ligne_n_a_pas_d_inscription(): void
    {
        $result = ReconciliationMatcher::match($this->line('WV123456', 'Aya Kouassi', 15000), $this->candidates([]));

        $this->assertSame(ReconciliationOutcome::NoRegistration, $result['outcome']);
        $this->assertNull($result['proof']);
    }

    public function test_une_preuve_en_especes_sans_reference_ne_se_rapproche_jamais_par_reference(): void
    {
        $proof = $this->proof(1, null, 15000, 'Aya Kouassi');

        // Ligne sans reference et sans nom proche : l'absence de reference des deux cotes ne
        // doit jamais compter comme une reference identique.
        $result = ReconciliationMatcher::match($this->line(null, 'Marcel Ouedraogo', 15000), $this->candidates([$proof]));

        $this->assertSame(ReconciliationOutcome::NoRegistration, $result['outcome']);
    }
}
