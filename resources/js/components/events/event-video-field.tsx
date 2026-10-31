import { router } from '@inertiajs/react';
import { Play, Trash2, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { useTranslation } from '@/hooks/use-translation';
import { formatFileSize } from '@/lib/format-file-size';
import { destroy, store } from '@/routes/tenants/events/video';

// La meme limite que `Event::PresentationVideoMaxKilobytes` : le navigateur refuse tout de suite, le serveur
// revalide quoi qu'il arrive.
const MaxBytes = 25 * 1024 * 1024;
const AcceptedTypes = ['video/mp4', 'video/webm'];

type Props = {
    tenantSlug: string;
    eventId: number;
    url: string | null;
    // La taille de la video enregistree, en octets.
    size: number | null;
};

type Upload = {
    name: string;
    size: number;
    loaded: number;
    percentage: number;
};

/**
 * La video courte de presentation de l'evenement (decision du 2026-10-31), affichee sur le lien public. Meme
 * comportement que `EventVisualField` (choisir, remplacer, retirer), avec en plus la taille de la video, le
 * refus immediat d'un fichier trop lourd ou d'un mauvais type, et la progression de l'envoi.
 */
export default function EventVideoField({
    tenantSlug,
    eventId,
    url,
    size,
}: Props) {
    const { t, locale } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const preview = useRef<HTMLVideoElement>(null);
    // Les commandes natives ne s'affichent qu'une fois la lecture de l'apercu lancee.
    const [previewing, setPreviewing] = useState(false);
    const [upload, setUpload] = useState<Upload | null>(null);
    const [removing, setRemoving] = useState(false);
    const [error, setError] = useState<string>();
    const [confirmingRemoval, setConfirmingRemoval] = useState(false);

    const processing = upload !== null || removing;
    const uploading = upload !== null;

    // Actualiser ou fermer l'onglet pendant l'envoi l'annulerait : le navigateur demande confirmation.
    useEffect(() => {
        if (!uploading) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };

        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [uploading]);
    // A 100 %, tout est parti : reste l'enregistrement chez le serveur, qui peut durer quelques secondes.
    const finishing = upload !== null && upload.percentage >= 100;

    const send = (chosen: File) => {
        setError(undefined);

        if (!AcceptedTypes.includes(chosen.type)) {
            setError(t('events.video.errors.type'));

            return;
        }

        if (chosen.size > MaxBytes) {
            setError(
                t('events.video.errors.too_large_chosen', {
                    size: formatFileSize(chosen.size, locale),
                    max: formatFileSize(MaxBytes, locale),
                }),
            );

            return;
        }

        router.post(
            store([tenantSlug, eventId]).url,
            { file: chosen },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () =>
                    setUpload({
                        name: chosen.name,
                        size: chosen.size,
                        loaded: 0,
                        percentage: 0,
                    }),
                onProgress: (progress) =>
                    setUpload({
                        name: chosen.name,
                        size: chosen.size,
                        loaded: progress?.loaded ?? 0,
                        percentage: progress?.percentage ?? 0,
                    }),
                onError: (errors) => setError(errors.file),
                onFinish: () => {
                    setUpload(null);

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
            onStart: () => setRemoving(true),
            onSuccess: () => setConfirmingRemoval(false),
            onFinish: () => setRemoving(false),
        });
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor="event-video">{t('events.video.label')}</Label>

            <div className="flex flex-wrap items-start gap-4">
                <div className="grid shrink-0 gap-1">
                    <div className="bg-muted relative flex aspect-video w-56 items-center justify-center overflow-hidden rounded-md">
                        {url ? (
                            <>
                                {/* Sans commandes natives : sur un apercu de cette taille, elles le recouvrent. Elles
                                    n'apparaissent qu'une fois la lecture lancee. `#t=0.1` montre une image de la video
                                    avant la lecture (Safari n'en affiche aucune sans cela). */}
                                <video
                                    ref={preview}
                                    src={`${url}#t=0.1`}
                                    controls={previewing}
                                    preload="metadata"
                                    playsInline
                                    aria-label={t('events.video.label')}
                                    className="size-full object-cover"
                                    data-test="event-video-preview"
                                    onPause={() => setPreviewing(false)}
                                />
                                {previewing ? null : (
                                    <button
                                        type="button"
                                        className="absolute inset-0 flex items-center justify-center bg-black/20 transition-colors hover:bg-black/30 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                                        aria-label={t('events.video.play')}
                                        data-test="event-video-play"
                                        onClick={() => {
                                            setPreviewing(true);
                                            void preview.current?.play();
                                        }}
                                    >
                                        <span className="flex size-10 items-center justify-center rounded-full bg-white/90 text-black">
                                            <Play className="size-5 fill-current" />
                                        </span>
                                    </button>
                                )}
                            </>
                        ) : (
                            <span className="text-muted-foreground text-xs">
                                {t('events.video.empty')}
                            </span>
                        )}
                    </div>
                    {url && size !== null ? (
                        <p
                            className="text-muted-foreground text-xs"
                            data-test="event-video-size"
                        >
                            {t('events.video.current_size', {
                                size: formatFileSize(size, locale),
                            })}
                        </p>
                    ) : null}
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

            {upload ? (
                <div
                    className="grid max-w-md gap-1"
                    role="status"
                    data-test="event-video-uploading"
                >
                    <p className="truncate text-sm font-medium">
                        {upload.name}
                    </p>
                    <Progress
                        value={upload.percentage}
                        aria-label={t('events.video.uploading')}
                    />
                    <p className="text-muted-foreground text-xs">
                        {finishing
                            ? t('events.video.finishing')
                            : t('events.video.progress', {
                                  loaded: formatFileSize(upload.loaded, locale),
                                  total: formatFileSize(upload.size, locale),
                                  percent: Math.round(upload.percentage),
                              })}
                    </p>
                </div>
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
                processing={removing}
                destructive
                testId="event-video-remove-confirm"
            />
        </div>
    );
}
