import { useState } from 'react';
import PhoneInput from 'react-phone-number-input';
import type { Country, Value } from 'react-phone-number-input';
import en from 'react-phone-number-input/locale/en.json';
import fr from 'react-phone-number-input/locale/fr.json';
import { CountrySelect } from '@/components/phone/country-select';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    id: string;
    name: string;
    // Pays deduit de l'adresse IP par le serveur (`App\Support\VisitorCountry`), CI a defaut.
    defaultCountry: Country;
    testId?: string;
    required?: boolean;
    // Le numero deja enregistre, sous sa forme unique (`+2250707123456`), pour le modifier.
    defaultValue?: string | null;
};

const countryNames = { fr, en } as const;

/**
 * Un telephone (invite ou membre), avec le pays et son drapeau (decision du proprietaire du projet,
 * 2026-09-29). Le champ visible montre le numero dans l'ecriture du pays choisi, sans l'indicatif :
 * c'est un champ cache qui envoie la forme complete (`+33612345678`), sans quoi un « 06 » saisi
 * sous le drapeau francais arriverait au serveur sans son pays. Le serveur revalide et normalise de
 * toute facon (`PhoneNumber::normalize`) : ce champ aide a la saisie, il ne garantit rien.
 */
export function PhoneField({
    id,
    name,
    defaultCountry,
    testId,
    required = false,
    defaultValue = null,
}: Props) {
    const { locale } = useTranslation();
    const [value, setValue] = useState<Value | undefined>(
        defaultValue?.startsWith('+') ? defaultValue : undefined,
    );

    return (
        <>
            <PhoneInput
                id={id}
                value={value}
                onChange={setValue}
                defaultCountry={defaultCountry}
                labels={countryNames[locale]}
                countrySelectComponent={CountrySelect}
                inputComponent={Input}
                addInternationalOption={false}
                autoComplete="tel"
                required={required}
                data-test={testId}
                // Un seul cadre, qui reprend celui de `Input` : le pays et le numero se lisent comme
                // un seul champ, et l'anneau de focus entoure l'ensemble.
                className="border-input focus-within:border-ring focus-within:ring-ring/50 flex h-9 w-full items-stretch rounded-md border shadow-xs transition-[color,box-shadow] focus-within:ring-[3px]"
                numberInputProps={{
                    className:
                        'h-full rounded-none border-0 bg-transparent shadow-none focus-visible:ring-0',
                }}
            />
            <input type="hidden" name={name} value={value ?? ''} />
        </>
    );
}
