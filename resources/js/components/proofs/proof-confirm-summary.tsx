import { formatPhoneNumberIntl } from 'react-phone-number-input';
import { ConfirmSummary } from '@/components/confirm-summary';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import type { PaymentProofRow, PaymentProofSignals } from '@/types';

type Props = {
    proof: PaymentProofRow;
    // Prefixe des reperes de test : `approve` ou `reject`.
    testId: string;
};

// `guestNote` est aussi un signal, mais une information a lire plutot qu'une anomalie : la fiche
// affiche la precision elle-meme plus bas.
type Anomaly = Exclude<keyof PaymentProofSignals, 'guestNote'>;

const AnomalyLabels: [Anomaly, string][] = [
    ['duplicateReference', 'proofs.signals.duplicate_reference'],
    ['duplicateImage', 'proofs.signals.duplicate_image'],
    [
        'referenceMissingFromStatement',
        'proofs.signals.reference_missing_from_statement',
    ],
    ['statementAmountMismatch', 'proofs.signals.statement_amount_mismatch'],
];

/**
 * La fiche de la preuve rappelee dans les confirmations de validation et de rejet. La validation ne
 * s'annule pas, le rejet oblige l'invite a deposer de nouveau, et deux inscrits voisins dans la file
 * se ressemblent (meme montant, meme compte) : la confirmation doit designer la preuve sans
 * ambiguite, dossier et telephone compris, et rappeler les anomalies qui motivent souvent la decision.
 */
export function ProofConfirmSummary({ proof, testId }: Props) {
    const { t, locale } = useTranslation();
    const anomalies = AnomalyLabels.filter(
        ([signal]) => proof.signals[signal],
    ).map(([, key]) => t(key));

    return (
        <ConfirmSummary
            testId={`${testId}-summary`}
            items={[
                {
                    label: t('proofs.columns.name'),
                    value: proof.name,
                    emphasis: true,
                },
                {
                    label: t('proofs.confirm.registration'),
                    value: proof.registrationReference,
                    mono: true,
                    hidden: proof.registrationReference === null,
                },
                {
                    label: t('proofs.confirm.phone'),
                    value: formatPhoneNumberIntl(proof.phone) || proof.phone,
                },
                {
                    label: t('proofs.columns.party_size'),
                    value: `${proof.partySize} (${proof.unit})`,
                },
                {
                    label: t('proofs.columns.amount_due'),
                    value: formatAmount(proof.amountDue, locale),
                    emphasis: true,
                },
                {
                    label: t('proofs.columns.payment_account'),
                    value: `${proof.paymentAccountLabel} (${proof.channelLabel})`,
                },
                {
                    label: t('proofs.columns.reference'),
                    value: proof.reference ?? '-',
                    mono: true,
                },
                {
                    label: t('proofs.columns.submitted_at'),
                    value: proof.submittedAt
                        ? formatDateTime(proof.submittedAt, locale)
                        : null,
                    hidden: proof.submittedAt === null,
                },
                {
                    label: t('proofs.columns.signals'),
                    value: anomalies.join(', '),
                    warning: true,
                    hidden: anomalies.length === 0,
                },
                {
                    label: t('proofs.columns.guest_note'),
                    value: proof.guestNote,
                    hidden: proof.guestNote === null,
                    testId: `${testId}-guest-note`,
                },
            ]}
        />
    );
}
