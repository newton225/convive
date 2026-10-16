import { Checkbox } from '@/components/ui/checkbox';

type Props = {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    label: string;
    testId: string;
};

/**
 * La case « Une seule ligne ouverte a la fois » des tableaux a lignes depliables (voir
 * `useExpandedRows`), la meme sur la file des preuves et la base d'inscrits.
 */
export function SingleExpandToggle({
    checked,
    onCheckedChange,
    label,
    testId,
}: Props) {
    return (
        <label className="flex min-h-9 items-center gap-2 text-sm">
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => onCheckedChange(value === true)}
                data-test={testId}
            />
            {label}
        </label>
    );
}
