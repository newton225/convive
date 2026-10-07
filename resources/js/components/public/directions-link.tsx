import { Navigation } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    href: string;
};

/**
 * « Voir l'itinéraire » : ouvre le lieu dans l'application de cartes du téléphone. Le lien vient de
 * l'organisateur et a été vérifié par le serveur (`MapLink`) : un service de cartes connu.
 */
export function DirectionsLink({ href }: Props) {
    const { t } = useTranslation();

    return (
        <Button variant="outline" size="sm" asChild>
            <a
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                data-test="event-directions"
            >
                <Navigation className="size-4" />
                {t('guest.event.directions')}
            </a>
        </Button>
    );
}
