import { ReceiptImage } from '@/components/proofs/receipt-image';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Props = {
    url: string;
    name: string;
    onEnlarge: () => void;
    className?: string;
};

/**
 * Vignette d'un recu, pour comparer plusieurs captures d'un coup d'oeil ; un clic l'agrandit.
 */
export function ReceiptThumbnail({ url, name, onEnlarge, className }: Props) {
    const { t } = useTranslation();

    return (
        <button
            type="button"
            className={cn(
                // Cadre neutre et fixe : une capture carree ou tres haute s'y centre sans bandes
                // sombres, et toutes les vignettes ont la meme taille pour comparer.
                'focus-visible:ring-ring/50 bg-muted/60 hover:border-foreground/30 block min-h-11 cursor-zoom-in overflow-hidden rounded-md border p-1.5 transition-colors outline-none focus-visible:ring-[3px]',
                className,
            )}
            aria-label={t('proofs.preview.enlarge', { name })}
            data-test="receipt-thumbnail"
            onClick={onEnlarge}
        >
            <ReceiptImage
                url={url}
                alt={t('proofs.preview.alt', { name })}
                className="h-full w-full rounded-sm bg-transparent"
                retryable={false}
            />
        </button>
    );
}
