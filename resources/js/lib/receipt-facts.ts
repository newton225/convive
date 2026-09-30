import { formatPhoneNumberIntl } from 'react-phone-number-input';
import type { ReceiptFact } from '@/components/proofs/receipt-preview-dialog';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import type {
    DuplicateImageMatch,
    LocaleCode,
    PaymentProofRow,
    RegistrationRow,
    TranslationReplacements,
} from '@/types';

type Translate = (
    key: string,
    replacements?: TranslationReplacements,
) => string;

/**
 * Les fiches affichees a cote d'un recu agrandi, selon l'ecran d'ou il est ouvert. Une valeur vide
 * est omise par la fenetre d'apercu.
 */
export function proofReceiptFacts(
    proof: PaymentProofRow,
    t: Translate,
    locale: LocaleCode,
): ReceiptFact[] {
    return [
        {
            id: 'registration',
            label: t('proofs.confirm.registration'),
            value: proof.registrationReference ?? '',
            mono: true,
        },
        {
            id: 'phone',
            label: t('proofs.confirm.phone'),
            value: formatPhoneNumberIntl(proof.phone) || proof.phone,
        },
        {
            id: 'amount_due',
            label: t('proofs.columns.amount_due'),
            value: formatAmount(proof.amountDue, locale),
        },
        {
            id: 'payment_account',
            label: t('proofs.columns.payment_account'),
            value: `${proof.paymentAccountLabel} (${proof.channelLabel})`,
        },
        {
            id: 'reference',
            label: t('proofs.columns.reference'),
            value: proof.reference ?? '',
            mono: true,
        },
        {
            id: 'submitted_at',
            label: t('proofs.columns.submitted_at'),
            value: proof.submittedAt
                ? formatDateTime(proof.submittedAt, locale)
                : '',
        },
    ];
}

export function duplicateMatchReceiptFacts(
    match: DuplicateImageMatch,
    t: Translate,
    locale: LocaleCode,
): ReceiptFact[] {
    return [
        {
            id: 'registration',
            label: t('proofs.confirm.registration'),
            value: match.registrationReference ?? '',
            mono: true,
        },
        {
            id: 'event',
            label: t('proofs.preview.event'),
            value: match.sameEvent
                ? t('proofs.duplicate_image.same_event')
                : match.eventName,
        },
        {
            id: 'status',
            label: t('proofs.preview.status'),
            value: match.statusLabel,
        },
        {
            id: 'reference',
            label: t('proofs.columns.reference'),
            value: match.reference ?? '',
            mono: true,
        },
        {
            id: 'submitted_at',
            label: t('proofs.columns.submitted_at'),
            value: match.submittedAt
                ? formatDateTime(match.submittedAt, locale)
                : '',
        },
    ];
}

export function registrationReceiptFacts(
    row: RegistrationRow,
    t: Translate,
    locale: LocaleCode,
): ReceiptFact[] {
    return [
        {
            id: 'registration',
            label: t('proofs.confirm.registration'),
            value: row.reference ?? '',
            mono: true,
        },
        {
            id: 'phone',
            label: t('proofs.confirm.phone'),
            value: formatPhoneNumberIntl(row.phone) || row.phone,
        },
        {
            id: 'amount_due',
            label: t('proofs.columns.amount_due'),
            value: formatAmount(row.amountDue, locale),
        },
        {
            id: 'status',
            label: t('proofs.preview.status'),
            value: row.statusLabel,
        },
        {
            id: 'channel',
            label: t('proofs.columns.channel'),
            value: row.channelLabel ?? '',
        },
        {
            id: 'reference',
            label: t('proofs.columns.reference'),
            value: row.proofReference ?? '',
            mono: true,
        },
        {
            id: 'submitted_at',
            label: t('proofs.columns.submitted_at'),
            value: row.proofSubmittedAt
                ? formatDateTime(row.proofSubmittedAt, locale)
                : '',
        },
    ];
}
