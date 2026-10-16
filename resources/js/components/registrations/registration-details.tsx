import { motion, useReducedMotion } from 'framer-motion';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import { Duration, EaseOut } from '@/lib/motion';
import type { RegistrationPerson, RegistrationRow } from '@/types';

type Props = {
    registration: RegistrationRow;
};

/**
 * Le detail d'un dossier, deplie sous sa ligne dans la base d'inscrits, construit comme celui de la
 * file des preuves (`ProofDetails`) : trois sections titrees, ouverture animee, ligne compacte tant
 * qu'on ne l'ouvre pas. Les accompagnateurs (avec unite, tarif et entree), le paiement, la carte et
 * l'entree. Rien qui ne soit deja dans la ligne : pas de
 * telephone, pas de lien, pas de precision de l'invite (elle vient de la preuve et suit son droit).
 */
export function RegistrationDetails({ registration }: Props) {
    const { t, locale } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const none = t('registrations.details.none');
    // L'invite est deja sur la ligne : le detail ne montre que ceux qui l'accompagnent, comme la file
    // des preuves.
    const companions = registration.people.filter((person) => !person.isGuest);

    const tariff = (person: RegistrationPerson) => {
        if (person.priceCategory === null) {
            return t('registrations.details.no_tariff');
        }

        const price =
            person.priceCategory.price === 0
                ? t('guest.registration.summary.free')
                : formatAmount(person.priceCategory.price, locale);

        return `${person.priceCategory.name} · ${price}`;
    };

    const entry = (person: RegistrationPerson) =>
        person.entered === null
            ? t('registrations.details.no_ticket')
            : person.entered
              ? t('registrations.details.entered')
              : t('registrations.details.not_entered');

    return (
        <motion.div
            initial={reduceMotion ? false : { opacity: 0, y: -6 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: Duration.quick, ease: EaseOut }}
            className="grid gap-6 px-2 py-3 text-sm lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]"
            data-test="registration-details-content"
        >
            <section className="space-y-2">
                <h3 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    {t('registrations.details.companions')}
                </h3>
                {companions.length === 0 ? (
                    <p
                        className="text-muted-foreground"
                        data-test="registration-no-companions"
                    >
                        {t('registrations.details.no_companions')}
                    </p>
                ) : (
                    <ol className="divide-y" data-test="registration-people">
                        {companions.map((person, index) => (
                            <li
                                key={`${person.name}-${index}`}
                                className="grid items-baseline gap-x-4 gap-y-1 py-2 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)]"
                                data-test="registration-person"
                            >
                                <span className="font-medium break-words">
                                    {person.name}
                                </span>
                                <span className="text-muted-foreground">
                                    {person.unit}
                                </span>
                                <span data-test="registration-person-tariff">
                                    {tariff(person)}
                                </span>
                                <span
                                    className="text-muted-foreground"
                                    data-test="registration-person-entry"
                                >
                                    {entry(person)}
                                </span>
                            </li>
                        ))}
                    </ol>
                )}
            </section>

            <section className="space-y-2" data-test="registration-payment">
                <h3 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    {t('registrations.details.payment')}
                </h3>
                <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1">
                    <dt className="text-muted-foreground">
                        {t('registrations.details.amount_due')}
                    </dt>
                    <dd>
                        {registration.amountDue === 0
                            ? t('guest.registration.summary.free')
                            : formatAmount(registration.amountDue, locale)}
                    </dd>
                    <dt className="text-muted-foreground">
                        {t('registrations.details.channel')}
                    </dt>
                    <dd>{registration.channelLabel ?? none}</dd>
                    <dt className="text-muted-foreground">
                        {t('registrations.details.reference')}
                    </dt>
                    <dd className="font-mono break-all">
                        {registration.proofReference ?? none}
                    </dd>
                    <dt className="text-muted-foreground">
                        {t('registrations.details.deposited_at')}
                    </dt>
                    <dd>
                        {registration.proofSubmittedAt
                            ? formatDateTime(
                                  registration.proofSubmittedAt,
                                  locale,
                              )
                            : none}
                    </dd>
                    {registration.rejection ? (
                        <>
                            <dt className="text-muted-foreground">
                                {t('registrations.details.rejection')}
                            </dt>
                            <dd
                                className="break-words"
                                data-test="registration-rejection"
                            >
                                {registration.rejection.reason}
                                {registration.rejection.note
                                    ? ` : ${registration.rejection.note}`
                                    : ''}
                            </dd>
                        </>
                    ) : null}
                    {registration.cancellationReason ? (
                        <>
                            <dt className="text-muted-foreground">
                                {t('registrations.details.cancellation')}
                            </dt>
                            <dd
                                className="break-words"
                                data-test="registration-cancellation"
                            >
                                {registration.cancellationReason}
                            </dd>
                        </>
                    ) : null}
                </dl>
            </section>

            <section className="space-y-2" data-test="registration-entry">
                <h3 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    {t('registrations.details.card_and_entry')}
                </h3>
                <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1">
                    <dt className="text-muted-foreground">
                        {t('registrations.card.column')}
                    </dt>
                    <dd>
                        {registration.cardSentAt
                            ? t('registrations.card.sent_on', {
                                  date: formatDateTime(
                                      registration.cardSentAt,
                                      locale,
                                  ),
                              })
                            : t('registrations.card.not_sent')}
                    </dd>
                    <dt className="text-muted-foreground">
                        {t('registrations.columns.entry')}
                    </dt>
                    <dd>
                        {registration.enteredAt
                            ? t('registrations.entry.group', {
                                  count: registration.enteredCount,
                                  total: registration.partySize,
                                  time: formatDateTime(
                                      registration.enteredAt,
                                      locale,
                                  ),
                              })
                            : t('registrations.entry.not_yet')}
                    </dd>
                </dl>
            </section>
        </motion.div>
    );
}
