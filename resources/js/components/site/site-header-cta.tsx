import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { register } from '@/routes';

/**
 * L'appel a l'action de l'en-tete : le seul bouton de la vitrine qui doit appeler le clic. Un degrade
 * indigo vif, une lueur, une fleche qui avance au survol et un reflet qui le traverse de temps en
 * temps (`site-cta-shine`, coupe avec `prefers-reduced-motion`). Les autres boutons restent sobres.
 */
export function SiteHeaderCta({ className }: { className?: string }) {
    const { t } = useTranslation();

    return (
        <Button
            size="sm"
            className={cn(
                'site-cta-shine group relative isolate overflow-hidden rounded-full border border-white/25 bg-[linear-gradient(135deg,oklch(0.6_0.18_262),oklch(0.5_0.2_285))] px-5 font-semibold text-white shadow-[0_0_26px_oklch(0.6_0.18_262/0.55),inset_0_1px_0_oklch(1_0_0/0.35)] transition-[transform,box-shadow] duration-200 hover:scale-[1.04] hover:text-white hover:shadow-[0_0_34px_oklch(0.65_0.18_262/0.75),inset_0_1px_0_oklch(1_0_0/0.4)] focus-visible:ring-2 focus-visible:ring-white/70 active:scale-[0.98]',
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
