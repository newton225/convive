import { Card, CardContent } from '@/components/ui/card';

type Props = {
    label: string;
    value: number;
    testId: string;
};

/**
 * Un chiffre cle du tableau de bord : libelle et valeur, rien d'autre. Le nombre est ecrit en
 * chiffres, jamais porte par une couleur.
 */
export function KpiCard({ label, value, testId }: Props) {
    return (
        <Card data-test={testId}>
            <CardContent className="space-y-1">
                <p className="text-muted-foreground text-sm">{label}</p>
                <p className="text-3xl font-semibold tracking-tight tabular-nums">
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}
