import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import type { Country } from 'react-phone-number-input';
import { getCountryCallingCode } from 'react-phone-number-input';
import flags from 'react-phone-number-input/flags';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type CountryOption = { value?: Country; label: string };

/**
 * Proprietes que `react-phone-number-input` passe a son `countrySelectComponent` : on ne
 * declare que celles dont on se sert.
 */
type Props = {
    value?: Country;
    onChange: (country?: Country) => void;
    options: CountryOption[];
    disabled?: boolean;
};

/**
 * La liste des pays du champ telephone, avec drapeau et recherche par nom ou par indicatif. Les
 * drapeaux sont des SVG embarques dans le paquet, jamais charges depuis un CDN : la CSP stricte
 * (SECURITY.md H7) n'a rien a autoriser.
 */
export function CountrySelect({ value, onChange, options, disabled }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const countries = options.filter(
        (option): option is Required<CountryOption> =>
            option.value !== undefined,
    );
    const current = countries.find((option) => option.value === value);
    const CurrentFlag = value ? flags[value] : undefined;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                {/* Integre au cadre du champ (`PhoneField`) : pas de bordure propre, seulement un
                    separateur a droite, et la meme hauteur que la saisie. */}
                <Button
                    type="button"
                    variant="ghost"
                    disabled={disabled}
                    className="h-full shrink-0 gap-1.5 rounded-none rounded-l-md border-r px-3 focus-visible:ring-inset"
                    aria-label={
                        current
                            ? t('guest.registration.phone_country.selected', {
                                  country: current.label,
                                  code: getCountryCallingCode(current.value),
                              })
                            : t('guest.registration.phone_country.label')
                    }
                    data-test="phone-country"
                >
                    <span className="flex h-4 w-6 overflow-hidden rounded-sm">
                        {CurrentFlag && current ? (
                            <CurrentFlag title={current.label} />
                        ) : null}
                    </span>
                    {current ? (
                        <span className="text-sm font-normal tabular-nums">
                            +{getCountryCallingCode(current.value)}
                        </span>
                    ) : null}
                    <ChevronsUpDown className="text-muted-foreground size-3.5" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-72 p-0" align="start">
                <Command>
                    <CommandInput
                        placeholder={t(
                            'guest.registration.phone_country.search',
                        )}
                    />
                    <CommandList>
                        <CommandEmpty>
                            {t('guest.registration.phone_country.empty')}
                        </CommandEmpty>
                        <CommandGroup>
                            {countries.map((option) => {
                                const Flag = flags[option.value];
                                const code = getCountryCallingCode(
                                    option.value,
                                );

                                return (
                                    <CommandItem
                                        key={option.value}
                                        value={`${option.label} +${code}`}
                                        onSelect={() => {
                                            onChange(option.value);
                                            setOpen(false);
                                        }}
                                        className="min-h-11 gap-2"
                                    >
                                        <span className="flex h-4 w-6 shrink-0 overflow-hidden rounded-sm">
                                            {Flag ? (
                                                <Flag title={option.label} />
                                            ) : null}
                                        </span>
                                        <span className="flex-1 truncate">
                                            {option.label}
                                        </span>
                                        <span className="text-muted-foreground text-sm tabular-nums">
                                            +{code}
                                        </span>
                                        <Check
                                            className={cn(
                                                'size-4',
                                                option.value === value
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
