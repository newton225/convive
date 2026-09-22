import { Info } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Le bandeau des ecrans construits avant leur serveur (CLAUDE.md, « Ordre de travail ») : un chiffre
 * d'exemple ne se presente jamais comme reel. Retire ecran par ecran, quand les vraies donnees
 * arrivent.
 */
export function SampleBanner() {
    const { t } = useTranslation();

    return (
        <div
            className="bg-muted text-muted-foreground flex items-start gap-3 rounded-lg p-3 text-sm"
            role="status"
            data-test="sample-banner"
        >
            <Info className="mt-0.5 size-4 shrink-0" />
            <div>
                <p className="text-foreground font-medium">
                    {t('common.sample.title')}
                </p>
                <p>{t('common.sample.description')}</p>
            </div>
        </div>
    );
}
