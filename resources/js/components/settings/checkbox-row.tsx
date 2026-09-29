import { HelpTip } from '@/components/help-tip';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    disabled?: boolean;
    hint?: string;
    // Explication de la regle, derriere un bouton d'aide en bout de ligne.
    help?: string;
};

/**
 * Une case a cocher avec son libelle : la cible tactile couvre toute la ligne (44 px au minimum,
 * CLAUDE.md), le libelle est relie a la case.
 */
export function CheckboxRow({
    id,
    label,
    checked,
    onChange,
    disabled = false,
    hint,
    help,
}: Props) {
    return (
        <div className="flex min-h-11 flex-col gap-1 py-1">
            <div className="flex items-center gap-3">
                <Checkbox
                    id={id}
                    checked={checked}
                    disabled={disabled}
                    onCheckedChange={(value) => onChange(value === true)}
                    data-test={id}
                />
                <Label
                    htmlFor={id}
                    className="flex-1 cursor-pointer py-2 aria-disabled:cursor-default"
                    aria-disabled={disabled}
                >
                    {label}
                </Label>
                {help ? <HelpTip subject={label}>{help}</HelpTip> : null}
            </div>
            {hint ? (
                <p className="text-muted-foreground pl-7 text-xs">{hint}</p>
            ) : null}
        </div>
    );
}
