import { CircleHelp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useProductTour } from '@/hooks/use-product-tour';
import { useTranslation } from '@/hooks/use-translation';
import type { ProductTourId } from '@/lib/product-tours';

type Props = {
    tour: ProductTourId;
    // Faux tant que la page n'est pas prete a etre montree (ecran de scan verrouille, par exemple).
    autoStart?: boolean;
};

/**
 * Le bouton « Revoir la visite » d'une page. Le poser suffit : il lance aussi la visite d'elle-meme
 * a la premiere ouverture.
 */
export function ProductTourButton({ tour, autoStart = true }: Props) {
    const { t } = useTranslation();
    const { start } = useProductTour(tour, autoStart);

    return (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={start}
            data-test="product-tour-start"
        >
            <CircleHelp />
            {t('tours.replay')}
        </Button>
    );
}
