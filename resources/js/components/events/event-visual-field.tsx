import { router } from '@inertiajs/react';
import { Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { destroy, store } from '@/routes/tenants/events/visual';

type Props = {
    tenantSlug: string;
    eventId: number;
    url: string | null;
    error?: string;
};

/**
 * README ecran 13 : le visuel propre a l'evenement, distinct des fichiers de marque de
 * l'organisation. Meme comportement que `BrandFileField` (choisir, remplacer, retirer), sur les
 * routes de l'evenement plutot que celles de l'organisation.
 */
export default function EventVisualField({
    tenantSlug,
    eventId,
    url,
    error,
}: Props) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);
    const [confirmingRemoval, setConfirmingRemoval] = useState(false);

    const send = (chosen: File) => {
        router.post(
            store([tenantSlug, eventId]).url,
            { file: chosen },
            {
                forceFormData: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
            },
        );
    };

    const remove = () => {
        router.delete(destroy([tenantSlug, eventId]).url, {
            onStart: () => setProcessing(true),
            onSuccess: () => setConfirmingRemoval(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor="event-visual">{t('events.fields.visual')}</Label>

            <div className="flex items-center gap-4">
                <div className="bg-muted flex h-20 w-32 shrink-0 items-center justify-center overflow-hidden rounded-md">
                    {url ? (
                        <img
                            src={url}
                            alt={t('events.fields.visual')}
                            className="size-full object-cover"
                        />
                    ) : (
                        <span className="text-muted-foreground text-xs">
                            {t('events.visual.empty')}
                        </span>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <input
                        ref={input}
                        id="event-visual"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="hidden"
                        data-test="event-visual-input"
                        onChange={(event) => {
                            const chosen = event.target.files?.[0];

                            if (chosen) {
                                send(chosen);
                            }
                        }}
                    />

                    <SubmitButton
                        type="button"
                        variant="secondary"
                        size="sm"
                        processing={processing}
                        onClick={() => input.current?.click()}
                    >
                        <Upload className="h-4 w-4" />
                        {url
                            ? t('events.visual.replace')
                            : t('events.visual.choose')}
                    </SubmitButton>

                    {url ? (
                        <SubmitButton
                            type="button"
                            variant="ghost"
                            size="sm"
                            processing={processing}
                            data-test="event-visual-remove"
                            onClick={() => setConfirmingRemoval(true)}
                        >
                            <Trash2 className="h-4 w-4" />
                            {t('events.visual.remove')}
                        </SubmitButton>
                    ) : null}
                </div>
            </div>

            <p className="text-muted-foreground text-xs">
                {t('events.visual.hint')}
            </p>
            <InputError message={error} />

            <ConfirmActionDialog
                open={confirmingRemoval}
                onOpenChange={setConfirmingRemoval}
                title={t('events.visual.remove_confirm.title')}
                description={t('events.visual.remove_confirm.description')}
                confirmLabel={t('events.visual.remove_confirm.confirm')}
                onConfirm={remove}
                processing={processing}
                destructive
                testId="event-visual-remove-confirm"
            />
        </div>
    );
}
