import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { treasury } from '@/routes/tenants';
import type { TreasuryFilters } from '@/types';

/**
 * La periode des totaux : les evenements dont la date tombe entre deux jours. Vide, toute la periode.
 */
export function TreasuryPeriodFilter({
    tenantSlug,
    filters,
}: {
    tenantSlug: string;
    filters: TreasuryFilters;
}) {
    const { t } = useTranslation();
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const apply = (next: TreasuryFilters) =>
        router.get(
            treasury(tenantSlug).url,
            { from: next.from || undefined, to: next.to || undefined },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <form
            className="flex flex-wrap items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                apply({ from, to });
            }}
            data-test="treasury-period"
        >
            <div className="space-y-1">
                <Label htmlFor="treasury-from">
                    {t('treasury.period.from')}
                </Label>
                <Input
                    id="treasury-from"
                    type="date"
                    value={from}
                    onChange={(event) => setFrom(event.target.value)}
                    className="min-h-11 w-44"
                />
            </div>
            <div className="space-y-1">
                <Label htmlFor="treasury-to">{t('treasury.period.to')}</Label>
                <Input
                    id="treasury-to"
                    type="date"
                    value={to}
                    onChange={(event) => setTo(event.target.value)}
                    className="min-h-11 w-44"
                />
            </div>
            <Button type="submit" className="min-h-11">
                {t('treasury.period.apply')}
            </Button>
            {filters.from || filters.to ? (
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11"
                    onClick={() => {
                        setFrom('');
                        setTo('');
                        apply({ from: null, to: null });
                    }}
                >
                    {t('treasury.period.reset')}
                </Button>
            ) : null}
            <p className="text-muted-foreground w-full text-xs">
                {t('treasury.period.hint')}
            </p>
        </form>
    );
}
