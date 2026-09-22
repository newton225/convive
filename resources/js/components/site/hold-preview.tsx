import { Timer } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Le compte a rebours de reservation (README 2.1), tel que l'invite le voit. Fige a 09:42 : c'est
 * une vignette, pas une horloge, et rien ici ne bouge donc rien a couper pour
 * `prefers-reduced-motion`.
 */
export function HoldPreview() {
    const { t } = useTranslation();

    return (
        <div data-test="site-hold-preview" className="space-y-3">
            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                <Timer className="size-4" />
                {t('site.preview.hold.label')}
            </div>
            <p className="text-4xl font-semibold tabular-nums">09:42</p>
            <div
                className="bg-muted h-1.5 overflow-hidden rounded-full"
                role="progressbar"
                aria-valuenow={97}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={t('site.preview.hold.label')}
            >
                <div className="bg-primary h-full w-[97%]" />
            </div>
            <p className="text-muted-foreground text-sm">
                {t('site.preview.hold.help')}
            </p>
        </div>
    );
}
