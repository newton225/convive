import { useFlashToast } from '@/hooks/use-flash-toast';
import { useVisitFeedback } from '@/hooks/use-visit-feedback';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();
    useVisitFeedback();

    return (
        // --normal-bg, --normal-text et --normal-border sont posees en CSS statique
        // (resources/css/app.css, selecteur .toaster) plutot qu'en style inline : une CSP
        // sans unsafe-inline (SECURITY.md H7) ne peut proteger que les balises
        // <style>/<script>, jamais un attribut style, et ces valeurs sont des constantes.
        <Sonner theme={appearance} className="toaster group" position="bottom-right" {...props} />
    );
}

export { Toaster };
