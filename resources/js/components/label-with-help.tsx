import { HelpTip } from '@/components/help-tip';
import { PublishMark } from '@/components/publish-mark';
import { Label } from '@/components/ui/label';

type Props = {
    htmlFor?: string;
    label: string;
    help?: string;
    required?: boolean;
    // Exige pour publier, pas pour enregistrer (`PublishMark`).
    forPublishing?: boolean;
};

/**
 * Un libelle de champ, suivi de son bouton d'aide quand le champ en a besoin. Sans `help`, c'est
 * un libelle ordinaire : les formulaires l'emploient partout sans se demander quel champ en a.
 */
export function LabelWithHelp({
    htmlFor,
    label,
    help,
    required,
    forPublishing = false,
}: Props) {
    const text = (
        <Label htmlFor={htmlFor} required={required}>
            {label}
            {forPublishing ? <PublishMark /> : null}
        </Label>
    );

    if (!help) {
        return text;
    }

    return (
        <div className="flex items-center gap-1.5 [&_button]:-my-[3px]">
            {text}
            <HelpTip subject={label}>{help}</HelpTip>
        </div>
    );
}
