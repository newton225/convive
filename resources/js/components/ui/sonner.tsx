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
        //
        // Sobre : le toast garde le fond neutre de l'interface, son type se lit a un lisere a
        // gauche et a son icone teintee (vert succes, rouge echec, bleu information, orange
        // avertissement) ; l'icone differe aussi d'un type a l'autre, la couleur n'est jamais
        // seule a porter l'information. Une notification neutre (`toast()`) reste telle quelle.
        // Point d'extension de sonner (`toastOptions.classNames`), avec le suffixe `!` de
        // Tailwind : la feuille de sonner, hors couche, l'emporterait sinon.
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            toastOptions={{
                classNames: {
                    success:
                        'border-l-4! border-l-emerald-600! [&_[data-icon]]:text-emerald-600! dark:[&_[data-icon]]:text-emerald-400!',
                    error: 'border-l-4! border-l-red-600! [&_[data-icon]]:text-red-600! dark:[&_[data-icon]]:text-red-400!',
                    info: 'border-l-4! border-l-blue-600! [&_[data-icon]]:text-blue-600! dark:[&_[data-icon]]:text-blue-400!',
                    warning:
                        'border-l-4! border-l-orange-600! [&_[data-icon]]:text-orange-600! dark:[&_[data-icon]]:text-orange-400!',
                },
            }}
            {...props}
        />
    );
}

export { Toaster };
