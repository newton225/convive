import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import type { RegistrationTotals as Totals } from '@/types';

/**
 * L'argent des dossiers du filtre actif, sous le tableau. Trois chiffres et non un seul : le montant du
 * compte aussi les dossiers pas encore payes, et ne doit pas se lire comme des recettes. L'encaisse est
 * le meme chiffre que le rapport de l'evenement.
 */
export function RegistrationTotals({ totals }: { totals: Totals }) {
    const { t, locale } = useTranslation();

    const figures = [
        {
            key: 'collected',
            label: t('registrations.totals.collected'),
            hint: t('registrations.totals.collected_hint'),
            amount: totals.collected,
        },
        {
            key: 'pending',
            label: t('registrations.totals.pending'),
            hint: t('registrations.totals.pending_hint'),
            amount: totals.pending,
        },
        {
            key: 'due',
            label: t('registrations.totals.due'),
            hint: t('registrations.totals.due_hint'),
            amount: totals.due,
        },
    ];

    return (
        <dl
            className="bg-card grid grid-cols-[repeat(auto-fit,minmax(12rem,1fr))] gap-4 rounded-lg p-4"
            data-test="registration-totals"
        >
            {figures.map((figure) => (
                <div
                    key={figure.key}
                    data-test={`registration-total-${figure.key}`}
                >
                    <dt className="text-muted-foreground text-sm">
                        {figure.label}
                    </dt>
                    <dd className="text-lg font-semibold">
                        {formatAmount(figure.amount, locale)}
                    </dd>
                    <dd className="text-muted-foreground text-xs">
                        {figure.hint}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
