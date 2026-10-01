import { HelpTip } from '@/components/help-tip';
import { Card, CardContent } from '@/components/ui/card';

type Props = {
    label: string;
    value: number;
    // Ce qui donne du sens au chiffre (« +19 cette semaine »), facultatif.
    hint?: string | null;
    // Ce que le chiffre compte exactement, dans une bulle d'aide.
    help: string;
    testId: string;
};

/**
 * Un chiffre cle du tableau de bord : libelle, valeur, et une bulle qui dit ce qu'il compte. Le nombre est ecrit en
 * chiffres, jamais porte par une couleur.
 */
export function KpiCard({ label, value, hint = null, help, testId }: Props) {
    return (
        <Card data-test={testId}>
            <CardContent className="space-y-1">
                <div className="flex items-center justify-between gap-1.5">
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <HelpTip subject={label}>{help}</HelpTip>
                </div>
                <p className="text-3xl font-semibold tracking-tight tabular-nums">
                    {value}
                </p>
                {hint ? (
                    <p className="text-muted-foreground text-xs">{hint}</p>
                ) : null}
            </CardContent>
        </Card>
    );
}
