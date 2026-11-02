import { useEffect, useRef } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { hasFastConnection } from '@/lib/connection-speed';

type Props = {
    url: string;
};

/**
 * La video courte de presentation de l'evenement (decision du 2026-10-31), sur le lien public, avant le
 * formulaire. Elle ne se lance seule, et sans son, que si la connexion est rapide (debit mesure sur le debut
 * du fichier, voir `hasFastConnection`, decision du 2026-11-02) et que l'invite n'a ni economiseur de donnees
 * ni preference de mouvement reduit. Sinon rien n'est telecharge au-dela des metadonnees tant qu'il ne la
 * lance pas (donnees mobiles).
 */
export function EventPresentationVideo({ url }: Props) {
    const { t } = useTranslation();
    const video = useRef<HTMLVideoElement>(null);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        let cancelled = false;

        void hasFastConnection(url).then((fast) => {
            const element = video.current;

            if (!fast || cancelled || element === null) {
                return;
            }

            // Un navigateur ne lance une video seule que sans son ; l'invite l'active avec les commandes.
            element.muted = true;
            element.play().catch(() => undefined);
        });

        return () => {
            cancelled = true;
        };
    }, [url]);

    return (
        <section data-test="event-presentation-video">
            <video
                ref={video}
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
