import { Head, Link, router, usePage } from '@inertiajs/react';
import { useId, useState } from 'react';
import AlertError from '@/components/alert-error';
import Heading from '@/components/heading';
import { NonceStyle } from '@/components/nonce-style';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
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
import { formatMoney } from '@/lib/format-currency';
import { formatDate } from '@/lib/format-date';
import { can, Permission } from '@/lib/permissions';
import { edit } from '@/routes/tenants';
import {
    cancel,
    checkout,
    paymentMethod,
    show,
} from '@/routes/tenants/billing';
import type {
    BillingInvoice,
    BillingPlan,
    BillingQuota,
    BillingSubscription,
    BillingUsage,
    TenantPermissions,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string; name: string };
    permissions: TenantPermissions;
    plan: BillingPlan;
    currencies: string[];
    defaultCurrency: string;
    subscription: BillingSubscription | null;
    usage: BillingUsage;
    plans: BillingPlan[];
    invoices: BillingInvoice[];
    salesContactEmail: string;
};

const QuotaKeys = ['events', 'registrations', 'members'] as const;

/**
 * Une jauge de quota. Le pourcentage est aussi ecrit en toutes lettres : l'etat ne repose pas sur
 * la couleur seule.
 */
function QuotaGauge({ label, quota }: { label: string; quota: BillingQuota }) {
    const { t } = useTranslation();
    const ratio =
        quota.max === null || quota.max === 0
            ? 0
            : Math.min(100, Math.round((quota.used / quota.max) * 100));

    return (
        <div className="space-y-1" data-test="billing-quota">
            <div className="flex items-baseline justify-between text-sm">
                <span>{label}</span>
                <span className="text-muted-foreground">
                    {quota.max === null
                        ? t('billing.usage.unlimited', { used: quota.used })
                        : t('billing.usage.of', {
                              used: quota.used,
                              max: quota.max,
                          })}
                </span>
            </div>
            {quota.max !== null ? (
                <div
                    className="bg-muted h-2 overflow-hidden rounded-full"
                    role="progressbar"
                    aria-valuenow={quota.used}
                    aria-valuemin={0}
                    aria-valuemax={quota.max}
                    aria-label={label}
                >
                    <QuotaGaugeFill ratio={ratio} />
                </div>
            ) : null}
        </div>
    );
}

function QuotaGaugeFill({ ratio }: { ratio: number }) {
    // Largeur calculee a l'execution : posee via une balise <style> nonce'e et scopee, pas
    // l'attribut `style` (voir NonceStyle).
    const scopeClass = `quota-gauge-fill-${useId().replace(/:/g, '')}`;

    return (
        <div
            className={`${scopeClass} h-full ${ratio >= 100 ? 'bg-destructive' : 'bg-primary'}`}
        >
            <NonceStyle
                selector={`.${scopeClass}`}
                declarations={{ width: `${ratio}%` }}
            />
        </div>
    );
}

/**
 * README ecran 16 : l'abonnement, etape 10 de « Ordre de construction ». Consommation des quotas,
 * plans, moyen de paiement, factures. Aucune donnee de carte ici : choisir un plan ou changer de
 * moyen de paiement ouvre la page hebergee du fournisseur.
 */
export default function Billing({
    tenant,
    permissions,
    plan,
    currencies,
    defaultCurrency,
    subscription,
    usage,
    plans,
    invoices,
    salesContactEmail,
}: Props) {
    const { t, locale } = useTranslation();
    const { errors } = usePage().props;
    const canManage = can(permissions, Permission.BillingManage);
    const [currency, setCurrency] = useState(defaultCurrency);
    const [confirmingCancel, setConfirmingCancel] = useState(false);
    const [cancelling, setCancelling] = useState(false);

    // Gratuit (0), sur devis (aucun prix) ou prix fixe dans la devise choisie.
    const priceLabel = (item: BillingPlan) => {
        const price = item.prices[currency] ?? null;

        return price === null
            ? t('billing.plans.on_quote')
            : price === 0
              ? t('billing.plans.free')
              : t('billing.plans.per_month', {
                    price: formatMoney(price, currency, locale),
                });
    };
    const billingErrors = errors?.billing ? [String(errors.billing)] : [];

    const limit = (value: number | null) =>
        value === null ? t('billing.plans.unlimited') : String(value);

    return (
        <>
            <Head title={t('billing.title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title={t('billing.title')}
                        description={tenant.name}
                    />
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={edit(tenant.slug)}>
                            {t('billing.back')}
                        </Link>
                    </Button>
                </div>

                {billingErrors.length > 0 ? (
                    <AlertError errors={billingErrors} />
                ) : null}

                {subscription?.status === 'past_due' ? (
                    <div
                        className="bg-muted rounded-lg p-4 text-sm"
                        role="alert"
                        data-test="billing-past-due"
                    >
                        {t('billing.banner.past_due')}
                    </div>
                ) : null}

                {subscription?.status === 'suspended' ? (
                    <div
                        className="bg-destructive/10 rounded-lg p-4 text-sm"
                        role="alert"
                        data-test="billing-suspended"
                    >
                        {t('billing.banner.suspended')}
                    </div>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card data-test="billing-plan">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {plan.name}
                                {subscription ? (
                                    <Badge variant="secondary">
                                        {subscription.statusLabel}
                                    </Badge>
                                ) : null}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="text-muted-foreground">
                                {priceLabel(plan)}
                            </p>
                            {subscription?.currentPeriodEndsAt ? (
                                <p>
                                    {t('billing.subscription.renews', {
                                        date: formatDate(
                                            subscription.currentPeriodEndsAt,
                                            locale,
                                        ),
                                    })}
                                </p>
                            ) : null}
                            {subscription?.canceledAt ? (
                                <p data-test="billing-canceled">
                                    {t('billing.subscription.canceled', {
                                        date: formatDate(
                                            subscription.canceledAt,
                                            locale,
                                        ),
                                    })}
                                </p>
                            ) : null}
                            <p>
                                {subscription?.paymentMethod
                                    ? t('billing.subscription.payment_method', {
                                          brand:
                                              subscription.paymentMethod
                                                  .brand ?? '',
                                          last4: subscription.paymentMethod
                                              .last4,
                                      })
                                    : t(
                                          'billing.subscription.no_payment_method',
                                      )}
                            </p>

                            {canManage ? (
                                <div className="flex flex-wrap gap-2 pt-2">
                                    {subscription?.hasProviderCustomer ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            data-test="billing-payment-method"
                                            onClick={() =>
                                                router.post(
                                                    paymentMethod(tenant.slug)
                                                        .url,
                                                )
                                            }
                                        >
                                            {t(
                                                'billing.subscription.change_payment_method',
                                            )}
                                        </Button>
                                    ) : null}
                                    {subscription &&
                                    !subscription.canceledAt ? (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            data-test="billing-cancel"
                                            onClick={() =>
                                                setConfirmingCancel(true)
                                            }
                                        >
                                            {t('billing.subscription.cancel')}
                                        </Button>
                                    ) : null}
                                    {subscription &&
                                    !subscription.canceledAt ? (
                                        <ConfirmActionDialog
                                            open={confirmingCancel}
                                            onOpenChange={setConfirmingCancel}
                                            title={t(
                                                'billing.confirm_cancel.title',
                                            )}
                                            description={t(
                                                'billing.confirm_cancel.description',
                                            )}
                                            confirmLabel={t(
                                                'billing.subscription.cancel',
                                            )}
                                            processing={cancelling}
                                            destructive
                                            testId="billing-cancel-confirm"
                                            onConfirm={() =>
                                                router.post(
                                                    cancel(tenant.slug).url,
                                                    {},
                                                    {
                                                        onStart: () =>
                                                            setCancelling(true),
                                                        onFinish: () =>
                                                            setCancelling(
                                                                false,
                                                            ),
                                                        onSuccess: () =>
                                                            setConfirmingCancel(
                                                                false,
                                                            ),
                                                    },
                                                )
                                            }
                                        />
                                    ) : null}
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card data-test="billing-usage">
                        <CardHeader>
                            <CardTitle>{t('billing.usage.title')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {QuotaKeys.map((key) => (
                                <QuotaGauge
                                    key={key}
                                    label={t(`billing.usage.${key}`)}
                                    quota={usage[key]}
                                />
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Heading
                            variant="small"
                            title={t('billing.plans.title')}
                        />
                        {currencies.length > 1 ? (
                            <div className="flex items-center gap-2">
                                <span className="text-muted-foreground text-sm">
                                    {t('billing.plans.currency')}
                                </span>
                                <Select
                                    value={currency}
                                    onValueChange={setCurrency}
                                >
                                    <SelectTrigger
                                        className="w-28"
                                        data-test="billing-currency"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {currencies.map((code) => (
                                            <SelectItem key={code} value={code}>
                                                {code}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        ) : null}
                    </div>
                    <div className="grid gap-3 md:grid-cols-3">
                        {plans.map((item) => (
                            <Card
                                key={item.code}
                                data-test={`billing-plan-${item.code}`}
                            >
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        {item.name}
                                        {item.current ? (
                                            <Badge>
                                                {t('billing.plans.current')}
                                            </Badge>
                                        ) : null}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    <p
                                        className="text-muted-foreground"
                                        data-test={`billing-price-${item.code}`}
                                    >
                                        {priceLabel(item)}
                                    </p>
                                    <ul className="space-y-1">
                                        <li>
                                            {t('billing.plans.events', {
                                                count: limit(
                                                    item.maxActiveEvents,
                                                ),
                                            })}
                                        </li>
                                        <li>
                                            {t('billing.plans.registrations', {
                                                count: limit(
                                                    item.maxRegistrations,
                                                ),
                                            })}
                                        </li>
                                        <li>
                                            {t('billing.plans.members', {
                                                count: limit(item.maxMembers),
                                            })}
                                        </li>
                                        {item.hasReconciliation ? (
                                            <li>
                                                {t(
                                                    'billing.plans.reconciliation',
                                                )}
                                            </li>
                                        ) : null}
                                        {item.hasReports ? (
                                            <li>
                                                {t('billing.plans.reports')}
                                            </li>
                                        ) : null}
                                        {item.hasCustomDomain ? (
                                            <li>
                                                {t(
                                                    'billing.plans.custom_domain',
                                                )}
                                            </li>
                                        ) : null}
                                        {item.hasSso ? (
                                            <li>{t('billing.plans.sso')}</li>
                                        ) : null}
                                    </ul>
                                    {canManage && !item.current ? (
                                        item.prices[currency] ? (
                                            <Button
                                                size="sm"
                                                data-test={`billing-choose-${item.code}`}
                                                onClick={() =>
                                                    router.post(
                                                        checkout([
                                                            tenant.slug,
                                                            item.code,
                                                        ]).url,
                                                        { currency },
                                                    )
                                                }
                                            >
                                                {t('billing.plans.choose')}
                                            </Button>
                                        ) : (
                                            // Plan sur devis (README section 3) : rien a
                                            // souscrire en ligne, un lien de contact remplace
                                            // le bouton plutot que de ne rien afficher.
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                                data-test={`billing-contact-${item.code}`}
                                            >
                                                <a
                                                    href={`mailto:${salesContactEmail}`}
                                                >
                                                    {t('billing.plans.contact')}
                                                </a>
                                            </Button>
                                        )
                                    ) : null}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>

                <div className="space-y-3">
                    <Heading
                        variant="small"
                        title={t('billing.invoices.title')}
                    />
                    {invoices.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            {t('billing.invoices.empty')}
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('billing.invoices.number')}
                                    </TableHead>
                                    <TableHead>
                                        {t('billing.invoices.date')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('billing.invoices.amount')}
                                    </TableHead>
                                    <TableHead>
                                        {t('billing.invoices.status')}
                                    </TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoices.map((invoice) => (
                                    <TableRow
                                        key={invoice.id}
                                        data-test="billing-invoice"
                                    >
                                        <TableCell>{invoice.number}</TableCell>
                                        <TableCell>
                                            {formatDate(
                                                invoice.issuedAt,
                                                locale,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatMoney(
                                                invoice.amount,
                                                invoice.currency,
                                                locale,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {invoice.statusLabel}
                                        </TableCell>
                                        <TableCell>
                                            {invoice.hostedUrl ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={invoice.hostedUrl}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        {t(
                                                            'billing.invoices.open',
                                                        )}
                                                    </a>
                                                </Button>
                                            ) : null}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>
            </div>
        </>
    );
}

Billing.layout = (props: {
    tenant: { slug: string };
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'billing.title'),
            href: show(props.tenant.slug),
        },
    ],
});
