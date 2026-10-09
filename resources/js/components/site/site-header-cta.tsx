import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { register } from '@/routes';

/**
 * L'appel a l'action de l'en-tete : le seul bouton de la vitrine qui doit appeler le clic. Le bleu des
 * autres boutons (`primary`), une lueur de la meme teinte, une fleche qui avance au survol et un reflet qui le traverse de temps en
 * temps (`site-cta-shine`, coupe avec `prefers-reduced-motion`). Les autres boutons restent sobres.
 */
export function SiteHeaderCta({ className }: { className?: string }) {
    const { t } = useTranslation();

    return (
        <Button
            size="sm"
            className={cn(
                'site-cta-shine group bg-primary text-primary-foreground relative isolate overflow-hidden rounded-full px-5 font-semibold shadow-[0_0_26px_color-mix(in_oklch,var(--primary)_55%,transparent),inset_0_1px_0_oklch(1_0_0/0.4)] transition-[transform,box-shadow] duration-200 hover:scale-[1.04] hover:shadow-[0_0_36px_color-mix(in_oklch,var(--primary)_80%,transparent),inset_0_1px_0_oklch(1_0_0/0.5)] focus-visible:ring-2 focus-visible:ring-white/70 active:scale-[0.98]',
                className,
            )}
            asChild
        >
            <Link href={register()} data-test="site-register">
                {t('site.nav.register')}
                <ArrowRight className="size-4 transition-transform duration-200 group-hover:translate-x-0.5" />
            </Link>
        </Button>
    );
}
