import { HelpTip } from '@/components/help-tip';
import { Label } from '@/components/ui/label';

type Props = {
    htmlFor?: string;
    label: string;
    help?: string;
};

/**
 * Un libelle de champ, suivi de son bouton d'aide quand le champ en a besoin. Sans `help`, c'est
 * un libelle ordinaire : les formulaires l'emploient partout sans se demander quel champ en a.
 */
export function LabelWithHelp({ htmlFor, label, help }: Props) {
    if (!help) {
        return <Label htmlFor={htmlFor}>{label}</Label>;
    }

    return (
        <div className="flex items-center gap-1.5">
            <Label htmlFor={htmlFor}>{label}</Label>
            <HelpTip subject={label}>{help}</HelpTip>
        </div>
    );
}
