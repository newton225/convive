import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

type Props = React.ComponentProps<typeof Button> & {
    processing: boolean;
    // Renseigne seulement par un formulaire qui suit ses modifications (`isDirty` du `<Form>`
    // d'Inertia) : le bouton passe alors en couleur d'accent tant que quelque chose reste a
    // enregistrer, et un libelle le dit aussi, la couleur seule ne suffisant pas.
    dirty?: boolean;
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
    dirty,
    variant,
    children,
    ...props
}: Props) {
    const { t } = useTranslation();

    const button = (
        <Button
            type="submit"
            disabled={disabled || processing}
            aria-busy={processing}
            variant={dirty === false ? 'outline' : variant}
            {...props}
        >
            {processing ? <Spinner /> : null}
            {children}
        </Button>
    );

    if (dirty === undefined) {
        return button;
    }

    return (
        <div className="flex flex-wrap items-center gap-3">
            {button}
            <span
                className="text-muted-foreground text-sm"
                aria-live="polite"
                data-test="unsaved-changes"
            >
                {dirty ? t('common.unsaved_changes') : null}
            </span>
        </div>
    );
}
