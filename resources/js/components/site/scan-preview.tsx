import { motion, useReducedMotion } from 'framer-motion';
import { Check, RotateCcw } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { EaseOut } from '@/lib/motion';

/**
 * Le resultat d'un scan a l'entree (README ecran 26) : valide, ou deja scanne avec l'heure du
 * premier passage. Le libelle et l'icone portent le sens, pas la couleur. Les deux resultats
 * arrivent dans l'ordre des deux passages.
 */
export function ScanPreview() {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;

    const appear = (delay: number) => ({
        initial: reduceMotion ? false : { opacity: 0, y: 12 },
        whileInView: { opacity: 1, y: 0 },
        viewport: { once: true, margin: '-60px' },
        transition: { duration: 0.5, delay, ease: EaseOut },
    });

    return (
        <div data-test="site-scan-preview" className="space-y-2">
            <motion.div
                {...appear(0.2)}
                className="bg-primary/10 text-primary flex items-center gap-2 rounded-xl px-3 py-3 text-sm font-medium"
            >
                <Check className="size-4" />
                {t('site.preview.scan.accepted')}
            </motion.div>
            <motion.div
                {...appear(0.55)}
                className="bg-muted/60 flex items-center gap-2 rounded-xl px-3 py-3 text-sm"
            >
                <RotateCcw className="text-muted-foreground size-4" />
                {t('site.preview.scan.already')}
            </motion.div>
        </div>
    );
}
