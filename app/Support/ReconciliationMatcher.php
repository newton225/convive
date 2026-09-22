<?php

namespace App\Support;

use App\Enums\ReconciliationOutcome;
use App\Models\PaymentProof;
use App\Models\StatementLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Le rapprochement automatique d'une ligne de releve avec les preuves d'un evenement (README
 * 2.10) : par reference d'abord, puis par montant egal et nom approchant.
 *
 * Fonction pure sur les preuves candidates recues : ne lit jamais la base. Exclure les preuves
 * deja consommees par une ligne precedente du meme import est le role de l'Action appelante
 * (`ImportReconciliationStatement`).
 */
class ReconciliationMatcher
{
    /**
     * Similarite minimale des noms, en pourcentage. Compromis : plus bas, deux personnes
     * distinctes du meme montant se confondent ; plus haut, une simple faute de frappe du
     * titulaire du compte Mobile Money fait echouer le rapprochement.
     */
    public const NameSimilarityThreshold = 70;

    /**
     * Match the line against the candidate proofs.
     *
     * @param  Collection<int, PaymentProof>  $candidateProofs  Chacune avec sa relation `registration` chargee.
     * @return array{outcome: ReconciliationOutcome, proof: PaymentProof|null}
     */
    public static function match(StatementLine $line, Collection $candidateProofs): array
    {
        $reference = self::normalizeReference($line->reference);

        if ($reference !== null) {
            $byReference = $candidateProofs
                ->filter(fn (PaymentProof $proof) => self::normalizeReference($proof->reference) === $reference)
                ->values();

            // Une reference portee par plusieurs preuves est ambigue : la deviner reviendrait a
            // valider un paiement au hasard. Laissee a la resolution manuelle.
            if ($byReference->count() > 1) {
                return ['outcome' => ReconciliationOutcome::NoRegistration, 'proof' => null];
            }

            if ($byReference->count() === 1) {
                $proof = $byReference->first();

                return [
                    'outcome' => $proof->amount_declared === $line->amount
                        ? ReconciliationOutcome::Matched
                        : ReconciliationOutcome::AmountMismatch,
                    'proof' => $proof,
                ];
            }
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($candidateProofs as $proof) {
            if ($proof->amount_declared !== $line->amount) {
                continue;
            }

            $score = self::nameSimilarity($line->issuer, $proof->registration->name);

            if ($score >= self::NameSimilarityThreshold && $score > $bestScore) {
                $best = $proof;
                $bestScore = $score;
            }
        }

        return $best === null
            ? ['outcome' => ReconciliationOutcome::NoRegistration, 'proof' => null]
            : ['outcome' => ReconciliationOutcome::ApproximateName, 'proof' => $best];
    }

    /**
     * Normalize a transaction reference for comparison : trimmed, upper-cased, null when blank.
     * Une reference vide (versement en especes) n'est jamais une reference identique a une autre.
     */
    public static function normalizeReference(?string $reference): ?string
    {
        $normalized = mb_strtoupper(trim((string) $reference));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Get the similarity of two names, from 0 to 100.
     *
     * Sans casse, sans accents, sans ponctuation, et sans tenir compte de l'ordre des mots : les
     * releves Mobile Money listent souvent le nom de famille en premier.
     */
    private static function nameSimilarity(string $first, string $second): float
    {
        similar_text(self::normalizeName($first), self::normalizeName($second), $percent);

        return $percent;
    }

    private static function normalizeName(string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/', mb_strtolower(Str::ascii($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        sort($words);

        return implode(' ', $words);
    }
}
