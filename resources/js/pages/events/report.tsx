import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { can, Permission } from '@/lib/permissions';
import { index as eventsIndex } from '@/routes/tenants/events';
import { show } from '@/routes/tenants/events/report';
import { pdf } from '@/routes/tenants/events/report/export';
import type { EventReport, TenantPermissions, Translations } from '@/types';

type Props = {
    tenant: { slug: string };
    event: { id: number; name: string };
    permissions: TenantPermissions;
    report: EventReport;
};

/**
 * README ecran 22 : le rapport post-evenement, etape 9 de « Ordre de construction ». Recalcule a
 * chaque ouverture par `GenerateEventReport`, jamais stocke.
 */
export default function EventReportPage({
    tenant,
    event,
    permissions,
    report,
}: Props) {
    const { t, locale } = useTranslation();

    const cards = [
        {
            key: 'confirmed',
            label: t('reports.cards.confirmed'),
            value: String(report.confirmedRegistrations),
            detail: t('reports.cards.seats', { count: report.confirmedSeats }),
        },
        {
            key: 'present',
            label: t('reports.cards.present'),
            value: String(report.presentRegistrations),
            detail: t('reports.cards.seats', { count: report.presentSeats }),
        },
        {
            key: 'absent',
            label: t('reports.cards.absent'),
            value: String(report.absentRegistrations),
            detail: t('reports.cards.seats', { count: report.absentSeats }),
        },
        {
            key: 'collected',
            label: t('reports.cards.collected'),
            value: formatAmount(report.collectedAmount, locale),
            detail: null,
        },
    ];

    return (
        <>
            <Head title={t('reports.title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title={t('reports.title')}
                        description={event.name}
                    />

                    {can(permissions, Permission.ReportsExport) ? (
                        <Button
                            variant="outline"
                            size="sm"
                            data-test="report-export-pdf"
                            asChild
                        >
                            <a href={pdf([tenant.slug, event.id]).url}>
                                {t('reports.actions.export_pdf')}
                            </a>
                        </Button>
                    ) : null}
                </div>

                <div className="grid grid-cols-[repeat(auto-fit,minmax(0,1fr))] gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {cards.map((card) => (
                        <Card key={card.key} data-test={`report-${card.key}`}>
                            <CardHeader>
                                <CardTitle className="text-muted-foreground text-sm font-normal">
                                    {card.label}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-semibold">
                                    {card.value}
                                </p>
                                {card.detail ? (
                                    <p className="text-muted-foreground text-sm">
                                        {card.detail}
                                    </p>
                                ) : null}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <p className="text-sm" data-test="report-average-scan-interval">
                    <span className="text-muted-foreground">
                        {t('reports.cards.average_scan_interval')} :{' '}
                    </span>
                    {report.averageScanIntervalSeconds === null
                        ? t('reports.cards.not_available')
                        : t('reports.cards.seconds', {
                              count: report.averageScanIntervalSeconds,
                          })}
                </p>

                <div className="space-y-2">
                    <Heading variant="small" title={t('reports.units.title')} />

                    {report.units.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            {t('reports.units.empty')}
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('reports.units.unit')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('reports.units.confirmed')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('reports.units.present')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('reports.units.collected')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {report.units.map((unit) => (
                                    <TableRow
                                        key={unit.unit}
                                        data-test="report-unit-row"
                                    >
                                        <TableCell>{unit.unit}</TableCell>
                                        <TableCell className="text-right">
                                            {unit.confirmedRegistrations}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {unit.presentRegistrations}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatAmount(
                                                unit.collectedAmount,
                                                locale,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}

                    <p className="text-muted-foreground text-xs">
                        {t('reports.units.note')}
                    </p>
                </div>
            </div>
        </>
    );
}

EventReportPage.layout = (props: {
    tenant: { slug: string };
    event: { id: number };
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'events.title'),
            href: eventsIndex(props.tenant.slug),
        },
        {
            title: translate(props.translations, 'reports.title'),
            href: show([props.tenant.slug, props.event.id]),
        },
    ],
});
