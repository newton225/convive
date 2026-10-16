import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import type { RegistrationPerson, RegistrationRow } from '@/types';

type Props = {
    registration: RegistrationRow;
};

/**
 * Le detail d'un dossier, deplie sous sa ligne dans la base d'inscrits : chaque personne du groupe,
 * l'invite puis ses accompagnateurs, avec son unite, son tarif et son passage a l'entree. La ligne
 * reste compacte (un seul « Groupe : 3 »), l'organisateur ouvre celle qu'il examine.
 */
export function RegistrationDetails({ registration }: Props) {
    const { t, locale } = useTranslation();

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
        <section className="space-y-2 px-2 py-3 text-sm">
            <h3 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                {t('registrations.details.people', {
                    count: registration.people.length,
                })}
            </h3>
            <ol className="divide-y" data-test="registration-people">
                {registration.people.map((person, index) => (
                    <li
                        key={`${person.name}-${index}`}
                        className="grid items-baseline gap-x-4 gap-y-1 py-2 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)]"
                        data-test="registration-person"
                    >
                        <span className="break-words">
                            <span className="font-medium">{person.name}</span>
                            <span className="text-muted-foreground text-xs">
                                {' · '}
                                {t(
                                    person.isGuest
                                        ? 'registrations.details.guest'
                                        : 'registrations.details.companion',
                                )}
                            </span>
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
        </section>
    );
}
