import { useTranslation } from '@/hooks/use-translation';
import type { ConsoleQuota } from '@/types';

/**
 * Une consommation de quota, lue comme une phrase : « 187 sur 200 », ou « 2 310, sans limite ».
 */
export function QuotaUsage({ quota }: { quota: ConsoleQuota }) {
    const { t, locale } = useTranslation();
    const used = new Intl.NumberFormat(locale).format(quota.used);

    if (quota.max === null) {
        return <span>{t('console.usage.unlimited', { used })}</span>;
    }

    const nearLimit = quota.used >= quota.max * 0.9;

    return (
        <span className={nearLimit ? 'font-medium' : undefined}>
            {t('console.usage.of', {
                used,
                max: new Intl.NumberFormat(locale).format(quota.max),
            })}
        </span>
    );
}
