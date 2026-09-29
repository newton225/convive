import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { create } from '@/routes/public/registrations';
import { create as createWaitlistEntry } from '@/routes/public/waitlist';
import type { PublicEvent } from '@/types';

type Props = {
    event: PublicEvent;
    token: string;
    // Le bouton de la barre fixe du bas d'ecran : meme action, sans reperes de test en double.
    compact?: boolean;
};

/**
 * Ce que l'invite peut faire maintenant : s'inscrire, rejoindre la liste d'attente si c'est
 * complet (README 2.3), ou lire pourquoi les inscriptions sont fermees. L'etat est calcule cote
 * serveur (`acceptsRegistrations`, `isFull`...), ce composant ne fait que le refleter. Les
 * boutons reviennent a la ligne plutot que d'imposer leur largeur : dans la barre fixee du bas,
 * un libelle long debordait de l'ecran sur un petit telephone ou avec le zoom du navigateur.
 */
export function EventRegistrationState({
    event,
    token,
    compact = false,
}: Props) {
    const { t } = useTranslation();

    if (event.acceptsRegistrations) {
        return (
            <Button
                asChild
                size="lg"
                className="brand-fill group h-auto min-h-12 w-full rounded-full py-2 leading-tight whitespace-normal hover:opacity-90"
            >
                <Link
                    href={create(token)}
                    data-test={compact ? undefined : 'register-link'}
                >
                    {t('guest.event.register')}
                    <ArrowRight className="transition-transform duration-200 group-hover:translate-x-0.5" />
                </Link>
            </Button>
        );
    }

    if (event.isFull) {
        return (
            <div className="space-y-3 text-center">
                {compact ? null : (
                    <p className="text-sm font-medium">
                        {t('guest.event.seats.full')}
                    </p>
                )}
                <Button
                    asChild
                    size="lg"
                    variant="secondary"
                    className="h-auto min-h-12 w-full rounded-full py-2 leading-tight whitespace-normal"
                >
                    <Link
                        href={createWaitlistEntry(token)}
                        data-test={compact ? undefined : 'waitlist-link'}
                    >
                        {t('guest.waitlist.join')}
                    </Link>
                </Button>
            </div>
        );
    }

    return (
        <p className="text-muted-foreground text-center text-sm">
            {event.registrationDeadlineHasPassed
                ? t('guest.event.deadline.passed')
                : t('guest.event.registration_closed')}
        </p>
    );
}
