import { usePage } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';

export function SiteFooter() {
    const { t } = useTranslation();
    const { name } = usePage().props;

    return (
        <footer className="mx-auto flex w-full max-w-6xl flex-col gap-2 px-6 py-10 text-sm">
            <p className="font-semibold">{name}</p>
            <p className="text-muted-foreground max-w-md">
                {t('site.footer.tagline')}
            </p>
            <p className="text-muted-foreground">
                {new Date().getFullYear()} {name}. {t('site.footer.rights')}
            </p>
        </footer>
    );
}
