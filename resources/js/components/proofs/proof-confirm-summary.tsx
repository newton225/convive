import { formatPhoneNumberIntl } from 'react-phone-number-input';
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
        <dl
            className="bg-muted grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 rounded-lg p-3 text-sm"
            data-test={`${testId}-summary`}
        >
            <dt className="text-muted-foreground">
                {t('proofs.columns.name')}
            </dt>
            <dd className="font-medium">{proof.name}</dd>

            {proof.registrationReference ? (
                <>
                    <dt className="text-muted-foreground">
                        {t('proofs.confirm.registration')}
                    </dt>
                    <dd className="font-mono">{proof.registrationReference}</dd>
                </>
            ) : null}

            <dt className="text-muted-foreground">
                {t('proofs.confirm.phone')}
            </dt>
            <dd className="tabular-nums">
                {formatPhoneNumberIntl(proof.phone) || proof.phone}
            </dd>

            <dt className="text-muted-foreground">
                {t('proofs.columns.party_size')}
            </dt>
            <dd>
                {proof.partySize} ({proof.unit})
            </dd>

            <dt className="text-muted-foreground">
                {t('proofs.columns.amount_due')}
            </dt>
            <dd className="font-medium">
                {formatAmount(proof.amountDue, locale)}
            </dd>

            <dt className="text-muted-foreground">
                {t('proofs.columns.payment_account')}
            </dt>
            <dd>
                {proof.paymentAccountLabel} ({proof.channelLabel})
            </dd>

            <dt className="text-muted-foreground">
                {t('proofs.columns.reference')}
            </dt>
            <dd className="font-mono break-all">{proof.reference ?? '-'}</dd>

            {proof.submittedAt ? (
                <>
                    <dt className="text-muted-foreground">
                        {t('proofs.columns.submitted_at')}
                    </dt>
                    <dd>{formatDateTime(proof.submittedAt, locale)}</dd>
                </>
            ) : null}

            {anomalies.length > 0 ? (
                <>
                    <dt className="text-muted-foreground">
                        {t('proofs.columns.signals')}
                    </dt>
                    <dd className="text-destructive font-medium">
                        {anomalies.join(', ')}
                    </dd>
                </>
            ) : null}

            {proof.guestNote ? (
                <>
                    <dt className="text-muted-foreground">
                        {t('proofs.columns.guest_note')}
                    </dt>
                    <dd
                        className="break-words whitespace-pre-line"
                        data-test={`${testId}-guest-note`}
                    >
                        {proof.guestNote}
                    </dd>
                </>
            ) : null}
        </dl>
    );
}
