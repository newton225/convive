import { useTranslation } from '@/hooks/use-translation';

type Props = {
    url: string;
};

/**
 * La video courte de presentation de l'evenement (decision du 2026-10-31), sur le lien public, avant le
 * formulaire. Aucune lecture automatique : l'invite la lance, et rien n'est telecharge au-dela des
 * metadonnees tant qu'il ne l'a pas fait (donnees mobiles).
 */
export function EventPresentationVideo({ url }: Props) {
    const { t } = useTranslation();

    return (
        <section data-test="event-presentation-video">
            <video
                src={url}
                controls
                preload="metadata"
                playsInline
                aria-label={t('events.video.public_label')}
                className="aspect-video w-full rounded-2xl bg-black object-contain"
            />
        </section>
    );
}
