import { Card, CardContent } from '@/components/ui/card';

type Props = {
    label: string;
    value: number;
    // Ce qui donne du sens au chiffre (« +19 cette semaine »), facultatif.
    hint?: string | null;
    testId: string;
};

/**
 * Un chiffre cle du tableau de bord : libelle et valeur, rien d'autre. Le nombre est ecrit en
 * chiffres, jamais porte par une couleur.
 */
export function KpiCard({ label, value, hint = null, testId }: Props) {
    return (
        <Card data-test={testId}>
            <CardContent className="space-y-1">
                <p className="text-muted-foreground text-sm">{label}</p>
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
