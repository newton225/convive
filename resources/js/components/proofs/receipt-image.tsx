import { ImageOff, RotateCw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useReceiptImage } from '@/hooks/use-receipt-image';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Props = {
    url: string;
    alt: string;
    className?: string;
    // Faux dans une vignette deja cliquable : un bouton dans un bouton n'est pas valide, et
    // l'apercu agrandi propose son propre nouvel essai.
    retryable?: boolean;
};

/**
 * La capture d'un recu affichee dans la page, avec ses etats : chargement, echec avec nouvel essai.
 * Voir `useReceiptImage` pour la raison du passage par une URL `blob:`.
 */
export function ReceiptImage({ url, alt, className, retryable = true }: Props) {
    const { t } = useTranslation();
    const [attempt, setAttempt] = useState(0);
    const image = useReceiptImage(url, attempt);

    if (image.status === 'ready') {
        return (
            <img
                src={image.src}
                alt={alt}
                className={cn(
                    'bg-muted mx-auto rounded-md object-contain',
                    className,
                )}
                data-test="receipt-image"
            />
        );
    }

    if (image.status === 'error') {
        return (
            <div
                className={cn(
                    'bg-muted text-muted-foreground flex flex-col items-center justify-center gap-2 rounded-md text-center text-sm',
                    retryable && 'p-6',
                    className,
                )}
                role="alert"
                data-test="receipt-image-error"
            >
                <ImageOff className="size-6" aria-hidden />
                {/* Dans une vignette, le message entier deborderait : l'apercu agrandi le donne. */}
                <p className={retryable ? undefined : 'sr-only'}>
                    {t('proofs.preview.error')}
                </p>
                {retryable ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setAttempt((previous) => previous + 1)}
                    >
                        <RotateCw />
                        {t('proofs.preview.retry')}
                    </Button>
                ) : null}
            </div>
        );
    }

    return (
        <Skeleton
            className={cn('aspect-[3/4] w-full rounded-md', className)}
            aria-label={t('proofs.preview.loading')}
            data-test="receipt-image-loading"
        />
    );
}
