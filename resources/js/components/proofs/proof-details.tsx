import { motion, useReducedMotion } from 'framer-motion';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { Duration, EaseOut } from '@/lib/motion';
import type { PaymentProofRow } from '@/types';

type Props = {
    proof: PaymentProofRow;
};

/**
 * Le detail d'une preuve, deplie sous sa ligne dans la file de verification : ce qui ne tient pas
 * dans une ligne de tableau (liste des accompagnateurs, precision de l'invite en entier, detail du
 * paiement). La ligne reste compacte, le tresorier ouvre celle qu'il examine.
 */
export function ProofDetails({ proof }: Props) {
    const { t, locale } = useTranslation();
    const reduceMotion = useReducedMotion() === true;

    return (
        <motion.div
            initial={reduceMotion ? false : { opacity: 0, y: -6 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: Duration.quick, ease: EaseOut }}
            className="grid gap-6 px-2 py-3 text-sm sm:grid-cols-3"
            data-test="proof-details"
        >
            <section className="space-y-2">
                <h3 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    {t('proofs.details.companions')}
                </h3>
                {proof.companions.length === 0 ? (
                    <p className="text-muted-foreground">
                        {t('proofs.companions.none')}
                    </p>
                ) : (
                    <ol className="space-y-1" data-test="proof-companions">
                        {proof.companions.map((companion, index) => (
                            <li
                                key={`${companion.name}-${index}`}
                                className="flex items-baseline justify-between gap-3"
                            >
                                <span className="break-words">
                                    {companion.name}
                                </span>
                                <span className="text-muted-foreground shrink-0 text-xs">
                                    {companion.unit}
                                </span>
                            </li>
                        ))}
                    </ol>
                )}
            </section>

            <section className="space-y-2">
                <h3 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    {t('proofs.columns.guest_note')}
                </h3>
                <p
                    className={
                        proof.guestNote
                            ? 'break-words whitespace-pre-line'
                            : 'text-muted-foreground'
                    }
                    data-test="proof-guest-note"
                >
                    {proof.guestNote ?? t('proofs.details.no_note')}
                </p>
            </section>

            <section className="space-y-2">
                <h3 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    {t('proofs.columns.payment')}
                </h3>
                <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1">
                    <dt className="text-muted-foreground">
                        {t('proofs.columns.payment_account')}
                    </dt>
                    <dd>{proof.paymentAccountLabel}</dd>
                    <dt className="text-muted-foreground">
                        {t('proofs.columns.channel')}
                    </dt>
                    <dd>{proof.channelLabel}</dd>
                    <dt className="text-muted-foreground">
                        {t('proofs.columns.reference')}
                    </dt>
                    <dd className="font-mono break-all">
                        {proof.reference ?? '-'}
                    </dd>
                    {proof.submittedAt ? (
                        <>
                            <dt className="text-muted-foreground">
                                {t('proofs.columns.submitted_at')}
                            </dt>
                            <dd>{formatDateTime(proof.submittedAt, locale)}</dd>
                        </>
                    ) : null}
                </dl>
            </section>
        </motion.div>
    );
}
