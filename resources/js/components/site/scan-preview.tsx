import { Check, RotateCcw } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Le resultat d'un scan a l'entree (README ecran 26) : valide, ou deja scanne avec l'heure du
 * premier passage. Le libelle et l'icone portent le sens, pas la couleur.
 */
export function ScanPreview() {
    const { t } = useTranslation();

    return (
        <div data-test="site-scan-preview" className="space-y-2">
            <div className="bg-primary/10 text-primary flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium">
                <Check className="size-4" />
                {t('site.preview.scan.accepted')}
            </div>
            <div className="bg-muted/60 flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm">
                <RotateCcw className="text-muted-foreground size-4" />
                {t('site.preview.scan.already')}
            </div>
        </div>
    );
}
