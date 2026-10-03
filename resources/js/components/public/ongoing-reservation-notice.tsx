import { Link } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

/**
 * L'invite revenu sur le formulaire (bouton « retour ») alors que sa reservation court encore :
 * il la reprend plutot que d'en recommencer une, refusee pour le meme numero.
 */
export function OngoingReservationNotice({ href }: { href: string }) {
    const { t } = useTranslation();

    return (
        <Alert data-test="registration-ongoing">
            <Clock />
            <AlertTitle>{t('guest.registration.ongoing.title')}</AlertTitle>
            <AlertDescription className="space-y-3">
                <p>{t('guest.registration.ongoing.description')}</p>
                <Button
                    className="brand-fill min-h-11 hover:opacity-90"
                    asChild
                >
                    <Link href={href} data-test="registration-ongoing-resume">
                        {t('guest.registration.ongoing.action')}
                    </Link>
                </Button>
            </AlertDescription>
        </Alert>
    );
}
