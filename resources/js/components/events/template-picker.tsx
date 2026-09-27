import { Link } from '@inertiajs/react';
import { Check, FilePlus2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { cn } from '@/lib/utils';
import { create } from '@/routes/tenants/events';
import type { EventTemplateOption } from '@/types';

type Props = {
    tenantSlug: string;
    templates: EventTemplateOption[];
    activeId: number | null;
};

/**
 * « Partir d'un modele » (prototype Convive.dc.html) : un evenement deja organise reprend ses
 * tables, son tarif, ses accompagnateurs et ses comptes de versement. Le modele vierge laisse tout
 * a parametrer. Des liens, pas un etat local : l'adresse dit quel modele est applique.
 */
export function TemplatePicker({ tenantSlug, templates, activeId }: Props) {
    const { t, locale } = useTranslation();

    if (templates.length === 0) {
        return null;
    }

    const itemClass = (active: boolean) =>
        cn(
            'flex min-h-11 items-start justify-between gap-2 rounded-lg p-3 text-left text-sm transition-colors',
            active
                ? 'bg-primary text-primary-foreground'
                : 'bg-muted hover:bg-muted/70',
        );

    return (
        <Card data-test="event-template-picker">
            <CardHeader>
                <CardTitle className="text-base">
                    {t('events.templates.title')}
                </CardTitle>
                <p className="text-muted-foreground text-sm">
                    {t('events.templates.description')}
                </p>
            </CardHeader>
            <CardContent className="grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-2">
                {templates.map((template) => (
                    <Link
                        key={template.id}
                        href={create(tenantSlug, {
                            query: { from: template.id },
                        })}
                        className={itemClass(activeId === template.id)}
                        data-test="event-template"
                        preserveScroll
                    >
                        <span className="min-w-0">
                            <span className="block font-medium break-words">
                                {template.name}
                            </span>
                            <span className="block text-xs opacity-80">
                                {t('events.templates.meta', {
                                    tables: template.tableCount,
                                    price: formatAmount(
                                        template.pricePerPerson,
                                        locale,
                                    ),
                                })}
                            </span>
                        </span>
                        {activeId === template.id ? (
                            <Check className="size-4 shrink-0" />
                        ) : null}
                    </Link>
                ))}
                <Link
                    href={create(tenantSlug)}
                    className={itemClass(activeId === null)}
                    data-test="event-template-blank"
                    preserveScroll
                >
                    <span>
                        <span className="block font-medium">
                            {t('events.templates.blank')}
                        </span>
                        <span className="block text-xs opacity-80">
                            {t('events.templates.blank_hint')}
                        </span>
                    </span>
                    {activeId === null ? (
                        <Check className="size-4 shrink-0" />
                    ) : (
                        <FilePlus2 className="size-4 shrink-0" />
                    )}
                </Link>
            </CardContent>
        </Card>
    );
}
