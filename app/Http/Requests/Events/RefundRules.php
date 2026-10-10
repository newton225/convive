<?php

namespace App\Http\Requests\Events;

use App\Data\RefundDecision;
use App\Enums\PaymentChannel;
use App\Enums\RefundStatus;
use App\Models\Registration;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Regles du sort d'un paiement (README 2.11), partagees par l'annulation et par « marquer comme
 * rembourse » : les deux formulaires saisissent le meme remboursement, ils doivent le refuser
 * pour les memes raisons.
 */
final class RefundRules
{
    /**
     * Rules for a refund decision. `$optionalStatus` : l'annulation laisse choisir le sort (« a
     * rembourser » par defaut) ; marquer comme rembourse n'a qu'une issue, le remboursement.
     *
     * Les frais restent strictement sous le montant paye : rembourser se fait en tout ou rien,
     * des frais egaux au montant ne rembourseraient rien.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function for(Registration $registration, string $prefix, bool $optionalStatus): array
    {
        $refunded = $optionalStatus ? 'required_if:'.$prefix.'status,'.RefundStatus::Refunded->value : 'required';

        return [
            ...($optionalStatus ? [$prefix.'status' => ['nullable', Rule::enum(RefundStatus::class)]] : []),
            $prefix.'channel' => [$refunded, 'nullable', Rule::enum(PaymentChannel::class)],
            $prefix.'refunded_on' => [$refunded, 'nullable', 'date', 'before_or_equal:today'],
            $prefix.'fee' => [$refunded, 'nullable', 'integer', 'min:0', 'max:'.max(0, $registration->amount_due - 1)],
            $prefix.'reference' => ['nullable', 'string', 'max:100'],
            ...($optionalStatus ? [
                $prefix.'kept_reason' => ['required_if:'.$prefix.'status,'.RefundStatus::Kept->value, 'nullable', 'string', 'max:500'],
            ] : []),
        ];
    }

    /**
     * Build the decision from validated input. Sans statut, « a rembourser ».
     *
     * @param  array<string, mixed>  $data
     */
    public static function decision(array $data, ?RefundStatus $status = null): RefundDecision
    {
        $status ??= RefundStatus::tryFrom((string) ($data['status'] ?? '')) ?? RefundStatus::Due;

        return match ($status) {
            RefundStatus::Due => RefundDecision::due(),
            RefundStatus::Refunded => RefundDecision::refunded(
                PaymentChannel::from((string) $data['channel']),
                Carbon::parse((string) $data['refunded_on']),
                (int) $data['fee'],
                filled($data['reference'] ?? null) ? (string) $data['reference'] : null,
            ),
            RefundStatus::Kept => RefundDecision::kept((string) $data['kept_reason']),
        };
    }

    /**
     * Les noms des champs tels que l'ecran les affiche : sans eux, une erreur disait « Le champ fee est
     * obligatoire » (relevee par le parcours de bout en bout du 2026-10-10).
     *
     * @return array<string, string>
     */
    public static function attributes(string $prefix): array
    {
        return [
            $prefix.'status' => __('registrations.refund.title'),
            $prefix.'channel' => __('registrations.refund.fields.channel'),
            $prefix.'refunded_on' => __('registrations.refund.fields.refunded_on'),
            $prefix.'fee' => __('registrations.refund.fields.fee'),
            $prefix.'reference' => __('registrations.refund.fields.reference'),
            $prefix.'kept_reason' => __('registrations.refund.fields.kept_reason'),
        ];
    }

    /**
     * Messages that say what to do, rather than the framework's generic wording.
     *
     * @return array<string, string>
     */
    public static function messages(string $prefix): array
    {
        return [
            $prefix.'fee.max' => __('registrations.refund.errors.fee_too_high'),
            $prefix.'refunded_on.before_or_equal' => __('registrations.refund.errors.future_date'),
            $prefix.'kept_reason.required_if' => __('registrations.refund.errors.kept_reason_required'),
        ];
    }
}
