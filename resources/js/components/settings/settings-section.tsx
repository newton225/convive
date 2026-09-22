import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    title: string;
    description?: string;
    testId: string;
    children: ReactNode;
};

/**
 * Une section d'un ecran de reglages : un titre, une phrase d'explication, son contenu. Reutilisee
 * par les reglages d'evenement, et par tout ecran qui empile des sections.
 */
export function SettingsSection({
    title,
    description,
    testId,
    children,
}: Props) {
    return (
        <Card data-test={testId}>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
                {description ? (
                    <p className="text-muted-foreground text-sm">
                        {description}
                    </p>
                ) : null}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}
