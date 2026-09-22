import { router } from '@inertiajs/react';
import { Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
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
};

export default function BrandFileField({
    tenantSlug,
    file,
    url,
    error,
}: Props) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);

    const send = (chosen: File) => {
        router.post(
            store([tenantSlug, file.value]).url,
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
        router.delete(destroy([tenantSlug, file.value]).url, {
            onStart: () => setProcessing(true),
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
                            onClick={remove}
                        >
                            <Trash2 className="h-4 w-4" />
                            {t('organisation.files.remove')}
                        </SubmitButton>
                    ) : null}
                </div>
            </div>

            <p className="text-muted-foreground text-xs">{file.hint}</p>
            <InputError message={error} />
        </div>
    );
}
