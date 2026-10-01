import { router } from '@inertiajs/react';
import { Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { ImageCropDialog } from '@/components/image-crop-dialog';
import type { CropArea } from '@/components/image-crop-dialog';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { destroy, store } from '@/routes/tenants/organisation/files';
import type { BrandFileOption } from '@/types';

type Props = {
    tenantSlug: string;
    file: BrandFileOption;
    url: string | null;
    error?: string;
    // Repere affiche sur la zone de rognage, quand ce fichier en impose un.
    cropGuide?: ReactNode;
    cropGuideHint?: string;
};

export default function BrandFileField({
    tenantSlug,
    file,
    url,
    error,
    cropGuide,
    cropGuideHint,
}: Props) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);
    // Le fichier en cours de rognage, quand ce fichier de marque impose ses proportions.
    const [cropping, setCropping] = useState<File | null>(null);
    const [confirmingRemoval, setConfirmingRemoval] = useState(false);

    const resetInput = () => {
        if (input.current) {
            input.current.value = '';
        }
    };

    const send = (chosen: File, crop?: CropArea) => {
        router.post(
            store([tenantSlug, file.value]).url,
            crop ? { file: chosen, crop } : { file: chosen },
            {
                forceFormData: true,
                onStart: () => setProcessing(true),
                onSuccess: () => setCropping(null),
                onFinish: () => {
                    setProcessing(false);
                    resetInput();
                },
            },
        );
    };

    const remove = () => {
        router.delete(destroy([tenantSlug, file.value]).url, {
            onStart: () => setProcessing(true),
            onSuccess: () => setConfirmingRemoval(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor={`brand-file-${file.value}`}>{file.label}</Label>

            <div className="flex items-center gap-4">
                <div className="bg-muted flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-md">
                    {url ? (
                        <img
                            src={url}
                            alt={file.label}
                            className="size-full object-contain"
                        />
                    ) : (
                        <span className="text-muted-foreground text-xs">
                            {t('organisation.files.empty')}
                        </span>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <input
                        ref={input}
                        id={`brand-file-${file.value}`}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="hidden"
                        data-test={`brand-file-${file.value}`}
                        onChange={(event) => {
                            const chosen = event.target.files?.[0];

                            if (!chosen) {
                                return;
                            }

                            if (file.crop) {
                                setCropping(chosen);
                            } else {
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
                            ? t('organisation.files.replace')
                            : t('organisation.files.choose')}
                    </SubmitButton>

                    {url ? (
                        <SubmitButton
                            type="button"
                            variant="ghost"
                            size="sm"
                            processing={processing}
                            data-test={`brand-file-remove-${file.value}`}
                            onClick={() => setConfirmingRemoval(true)}
                        >
                            <Trash2 className="h-4 w-4" />
                            {t('organisation.files.remove')}
                        </SubmitButton>
                    ) : null}
                </div>
            </div>

            <p className="text-muted-foreground text-xs">{file.hint}</p>
            <InputError message={error} />

            <ConfirmActionDialog
                open={confirmingRemoval}
                onOpenChange={setConfirmingRemoval}
                title={t('organisation.files.remove_confirm.title', {
                    label: file.label,
                })}
                description={t('organisation.files.remove_confirm.description')}
                confirmLabel={t('organisation.files.remove_confirm.confirm')}
                onConfirm={remove}
                processing={processing}
                destructive
                testId={`brand-file-remove-confirm-${file.value}`}
            />

            {file.crop ? (
                <ImageCropDialog
                    file={cropping}
                    aspect={file.crop.width / file.crop.height}
                    title={t('organisation.files.crop.title', {
                        label: file.label,
                    })}
                    processing={processing}
                    guide={cropGuide}
                    guideHint={cropGuideHint}
                    onCancel={() => {
                        setCropping(null);
                        resetInput();
                    }}
                    onConfirm={(area) => {
                        if (cropping) {
                            send(cropping, area);
                        }
                    }}
                />
            ) : null}
        </div>
    );
}
