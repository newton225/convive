import { TriangleAlert } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type Props = {
    title: string;
    items: string[];
    id?: string;
    testId?: string;
};

/**
 * Une consigne du formulaire de depot de preuve : un titre, puis une courte liste, plutot qu'un
 * paragraphe dense que l'invite survole. Chaque ligne dit une seule chose.
 */
export function ProofRules({ title, items, id, testId }: Props) {
    return (
        <Alert id={id} role="note" data-test={testId}>
            <TriangleAlert />
            <AlertTitle className="line-clamp-none">{title}</AlertTitle>
            <AlertDescription>
                <ul className="list-disc space-y-1 pl-4">
                    {items.map((item) => (
                        <li key={item}>{item}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
