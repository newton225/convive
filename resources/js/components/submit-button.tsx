import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Props = React.ComponentProps<typeof Button> & {
    processing: boolean;
};

/**
 * Le bouton de soumission d'un formulaire, avec un etat de chargement visible (CLAUDE.md,
 * « Retour immediat sur chaque action » : une action sans retour laisse l'utilisateur cliquer
 * une seconde fois, ou douter que le clic a porte). Desactive et affiche une roue pendant
 * l'envoi, plutot que la seule desactivation silencieuse du bouton natif.
 */
export function SubmitButton({
    processing,
    disabled,
    children,
    ...props
}: Props) {
    return (
        <Button
            type="submit"
            disabled={disabled || processing}
            aria-busy={processing}
            {...props}
        >
            {processing ? <Spinner /> : null}
            {children}
        </Button>
    );
}
