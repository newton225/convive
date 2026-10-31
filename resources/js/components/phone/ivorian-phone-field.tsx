import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { formatIvorianDigits, ivorianNationalDigits } from '@/lib/guest-input';

type Props = {
    id: string;
    name: string;
    testId?: string;
    required?: boolean;
};

/**
 * Le telephone d'un invite : un numero ivoirien a dix chiffres (decision du proprietaire du projet,
 * 2026-10-31). L'indicatif +225 est fixe devant le champ, seuls des chiffres s'ecrivent, jamais plus de
 * dix. Un champ cache envoie la forme complete (`+2250707123456`) ; le serveur revalide quoi qu'il
 * arrive (`App\Rules\IvorianPhoneNumber`).
 */
export function IvorianPhoneField({
    id,
    name,
    testId,
    required = false,
}: Props) {
    const [digits, setDigits] = useState('');

    return (
        <>
            <div className="border-input focus-within:border-ring focus-within:ring-ring/50 flex h-9 w-full items-stretch rounded-md border shadow-xs transition-[color,box-shadow] focus-within:ring-[3px]">
                <span
                    className="text-muted-foreground flex items-center border-r px-3 text-sm"
                    aria-hidden="true"
                >
                    +225
                </span>
                <Input
                    id={id}
                    type="tel"
                    inputMode="numeric"
                    autoComplete="tel-national"
                    placeholder="07 00 00 00 00"
                    value={formatIvorianDigits(digits)}
                    onChange={(event) =>
                        setDigits(ivorianNationalDigits(event.target.value))
                    }
                    required={required}
                    data-test={testId}
                    className="h-full rounded-none border-0 bg-transparent shadow-none focus-visible:ring-0"
                />
            </div>
            <input
                type="hidden"
                name={name}
                value={digits ? `+225${digits}` : ''}
            />
        </>
    );
}
