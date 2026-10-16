import { Eye } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    // La personne dont on voit la carte.
    name: string;
};

/**
 * Le bandeau de l'apercu : l'organisation ouvre la carte d'un invite depuis sa fenetre « Carte »
 * (`?preview=1`) pour la voir avant de l'envoyer. La page ne parle alors plus a l'invite : ce n'est
 * pas la carte de celui qui la regarde, rien ne s'y envoie, et les gestes qui ne servent qu'a l'invite
 * (s'envoyer la carte, reclamer) disparaissent.
 */
export function PreviewBanner({ name }: Props) {
    const { t } = useTranslation();

    return (
        <p
            className="bg-muted text-foreground flex items-start gap-2 rounded-lg p-3 text-sm"
            role="status"
            data-test="preview-banner"
        >
            <Eye className="mt-0.5 size-4 shrink-0" />
            {t('guest.preview.banner', { name })}
        </p>
    );
}
