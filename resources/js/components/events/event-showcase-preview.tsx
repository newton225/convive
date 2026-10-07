import { ShowcaseCard } from '@/components/showcase/showcase-card';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    name: string;
    organisationName: string;
    startsAt: string | null;
    visualUrl: string | null;
};

/**
 * La carte de l'evenement telle qu'elle apparaitra dans la vitrine du site produit, mise a jour
 * pendant la saisie (decision du proprietaire du projet, 2026-10-07). La meme carte que la vitrine
 * (`ShowcaseCard`), rendue inerte : un apercu ne se clique pas.
 */
export function EventShowcasePreview({
    name,
    organisationName,
    startsAt,
    visualUrl,
}: Props) {
    const { t } = useTranslation();

    return (
        <section
            className="space-y-3"
            aria-labelledby="event-showcase-preview-title"
            data-test="event-showcase-preview"
        >
            <div>
                <h2
                    id="event-showcase-preview-title"
                    className="text-sm font-semibold"
                >
                    {t('events.preview.title')}
                </h2>
                <p className="text-muted-foreground text-xs">
                    {t('events.preview.description')}
                </p>
            </div>
            <div inert className="grid">
                <ShowcaseCard
                    index={0}
                    event={{
                        name: name.trim() || t('events.preview.untitled'),
                        organisationName,
                        startsAt,
                        publicUrl: '#',
                        visualUrl,
                    }}
                />
            </div>
        </section>
    );
}
