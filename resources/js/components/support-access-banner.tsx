import { Link, usePage } from '@inertiajs/react';
import { LifeBuoy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { show as supportAccessShow } from '@/routes/tenants/support-access';

/**
 * Le bandeau permanent d'un acces de support en cours (README section 3) : tant que l'equipe
 * Convive peut lire l'organisation, tous ses membres le voient sur chaque page du back-office. La
 * personne qui consulte sous cet acces voit, elle, qu'elle est en lecture seule et journalisee.
 * Seul un Proprietaire recoit le lien pour le gerer.
 */
export function SupportAccessBanner() {
    const { t, locale } = useTranslation();
    const { supportAccess, currentTenant } = usePage().props;

    if (!supportAccess || !currentTenant) {
        return null;
    }

    const expires = formatDateTime(supportAccess.expiresAt, locale);

    return (
        <div
            className="bg-muted flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-2 text-sm"
            role="status"
            data-test="support-access-banner"
        >
            <LifeBuoy className="size-4 shrink-0" aria-hidden />
            <p className="min-w-0 flex-1">
                {supportAccess.viewing
                    ? t('support_access.banner.viewing', {
                          organisation: currentTenant.name,
                          expires,
                      })
                    : t('support_access.banner.member', {
                          operator: supportAccess.operator,
                          expires,
                      })}
            </p>
            {!supportAccess.viewing && currentTenant.isOwner ? (
                <Button asChild variant="outline" size="sm">
                    <Link href={supportAccessShow(currentTenant.slug)}>
                        {t('support_access.banner.manage')}
                    </Link>
                </Button>
            ) : null}
        </div>
    );
}
