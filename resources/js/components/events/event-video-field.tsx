import { router } from '@inertiajs/react';
import { Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { destroy, store } from '@/routes/tenants/events/video';

type Props = {
    tenantSlug: string;
    eventId: number;
    url: string | null;
};

/**
 * La video courte de presentation de l'evenement (decision du 2026-10-31), affichee sur le lien public. Meme
 * comportement que `EventVisualField` (choisir, remplacer, retirer) ; l'erreur du serveur (type, taille) se
 * lit sous le champ.
 */
export default function EventVideoField({ tenantSlug, eventId, url }: Props) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string>();
    const [confirmingRemoval, setConfirmingRemoval] = useState(false);

    const send = (chosen: File) => {
        setError(undefined);

        router.post(
            store([tenantSlug, eventId]).url,
            { file: chosen },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onError: (errors) => setError(errors.file),
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
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => setConfirmingRemoval(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor="event-video">{t('events.video.label')}</Label>

            <div className="flex flex-wrap items-start gap-4">
                <div className="bg-muted flex aspect-video w-48 shrink-0 items-center justify-center overflow-hidden rounded-md">
                    {url ? (
                        <video
                            src={url}
                            controls
                            preload="metadata"
                            playsInline
                            aria-label={t('events.video.label')}
                            className="size-full object-cover"
                            data-test="event-video-preview"
                        />
                    ) : (
                        <span className="text-muted-foreground text-xs">
                            {t('events.video.empty')}
                        </span>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <input
                        ref={input}
                        id="event-video"
                        type="file"
                        accept="video/mp4,video/webm"
                        className="hidden"
                        data-test="event-video-input"
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
                            ? t('events.video.replace')
                            : t('events.video.choose')}
                    </SubmitButton>

                    {url ? (
                        <SubmitButton
                            type="button"
                            variant="ghost"
                            size="sm"
                            processing={processing}
                            data-test="event-video-remove"
                            onClick={() => setConfirmingRemoval(true)}
                        >
                            <Trash2 className="h-4 w-4" />
                            {t('events.video.remove')}
                        </SubmitButton>
                    ) : null}
                </div>
            </div>

            {processing ? (
                <p
                    className="text-muted-foreground text-xs"
                    role="status"
                    data-test="event-video-uploading"
                >
                    {t('events.video.uploading')}
                </p>
            ) : null}
            <p className="text-muted-foreground text-xs">
                {t('events.video.hint')}
            </p>
            <InputError message={error} />

            <ConfirmActionDialog
                open={confirmingRemoval}
                onOpenChange={setConfirmingRemoval}
                title={t('events.video.remove_confirm.title')}
                description={t('events.video.remove_confirm.description')}
                confirmLabel={t('events.video.remove_confirm.confirm')}
                onConfirm={remove}
                processing={processing}
                destructive
                testId="event-video-remove-confirm"
            />
        </div>
    );
}
