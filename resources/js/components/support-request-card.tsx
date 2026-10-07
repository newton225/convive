import { router, useForm } from '@inertiajs/react';
import { BellRing } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { destroy, store } from '@/routes/tenants/support-access/requests';
import type { PendingSupportRequest } from '@/types';

type Props = {
    tenantSlug: string;
    pendingRequest: PendingSupportRequest | null;
};

/**
 * La demande d'aide (README ecran 25) : quand personne de l'equipe Convive n'est visible, le
 * Proprietaire previent l'equipe et dit pourquoi. Une fois envoyee, la carte dit ou elle en est :
 * en attente, ou prise en charge par une personne a qui l'acces peut maintenant etre ouvert. La
 * demande n'ouvre rien par elle-meme.
 */
export function SupportRequestCard({ tenantSlug, pendingRequest }: Props) {
    const { t, locale } = useTranslation();
    const form = useForm({ reason: '' });
    const [cancelling, setCancelling] = useState(false);

    const send = () => {
        form.post(store(tenantSlug).url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const cancel = (requestId: number) => {
        router.delete(destroy([tenantSlug, requestId]).url, {
            preserveScroll: true,
            onStart: () => setCancelling(true),
            onFinish: () => setCancelling(false),
        });
    };

    return (
        <Card data-test="support-request">
            <CardHeader>
                <CardTitle>{t('support_access.request.title')}</CardTitle>
            </CardHeader>
            {pendingRequest ? (
                <CardContent className="space-y-3 text-sm">
                    <p className="font-medium" role="status">
                        {pendingRequest.takenBy
                            ? t('support_access.request.taken', {
                                  operator: pendingRequest.takenBy,
                              })
                            : t('support_access.request.sent', {
                                  date: formatDateTime(
                                      pendingRequest.requestedAt,
                                      locale,
                                  ),
                              })}
                    </p>
                    <p>
                        <span className="text-muted-foreground">
                            {t('support_access.request.your_message')} :
                        </span>{' '}
                        {pendingRequest.reason}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={cancelling}
                        onClick={() => cancel(pendingRequest.id)}
                        data-test="support-request-cancel"
                    >
                        {t('support_access.request.cancel')}
                    </Button>
                </CardContent>
            ) : (
                <CardContent className="space-y-4 text-sm">
                    <p className="text-muted-foreground">
                        {t('support_access.request.intro')}
                    </p>
                    <div className="grid gap-2">
                        <Label htmlFor="support-request-reason" required>
                            {t('support_access.request.reason')}
                        </Label>
                        <Textarea
                            id="support-request-reason"
                            name="reason"
                            rows={3}
                            maxLength={500}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                        <p className="text-muted-foreground text-xs">
                            {t('support_access.request.reason_hint')}
                        </p>
                        <InputError message={form.errors.reason} />
                    </div>
                    <SubmitButton
                        type="button"
                        processing={form.processing}
                        disabled={form.data.reason.trim() === ''}
                        onClick={send}
                        data-test="support-request-send"
                    >
                        <BellRing />
                        {t('support_access.request.submit')}
                    </SubmitButton>
                </CardContent>
            )}
        </Card>
    );
}
