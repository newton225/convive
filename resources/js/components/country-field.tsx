import { Check, ChevronsUpDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { getCountries } from 'react-phone-number-input';
import flags from 'react-phone-number-input/flags';
import en from 'react-phone-number-input/locale/en.json';
import fr from 'react-phone-number-input/locale/fr.json';
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
import { matchesSearch } from '@/lib/search';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    name: string;
    // Le code enregistre (`CI`), ou rien.
    defaultValue: string | null;
    testId?: string;
};

const countryNames = { fr, en } as const;

/**
 * Le pays d'une adresse, choisi dans une liste avec recherche plutot que saisi : le serveur
 * enregistre son code (`CI`), et c'est le code qui donne son nom dans la langue de chaque document.
 * La meme liste et les memes drapeaux embarques que le champ telephone (`CountrySelect`).
 */
export function CountryField({ id, name, defaultValue, testId }: Props) {
    const { t, locale } = useTranslation();
    const [open, setOpen] = useState(false);
    const [value, setValue] = useState(defaultValue?.toUpperCase() ?? '');
    const hiddenInput = useRef<HTMLInputElement>(null);
    const chosen = useRef(false);

    // Le `<Form>` d'Inertia repere une modification aux evenements `input` du formulaire, qu'un
    // champ cache n'emet pas : on le previent apres chaque choix.
    useEffect(() => {
        if (chosen.current) {
            hiddenInput.current?.dispatchEvent(
                new Event('input', { bubbles: true }),
            );
        }
    }, [value]);

    const labels = countryNames[locale];
    const countries = getCountries()
        .map((code) => ({ code, label: labels[code] ?? code }))
        .sort((a, b) => a.label.localeCompare(b.label, locale));
    const current = countries.find((country) => country.code === value);
    const CurrentFlag = current ? flags[current.code] : undefined;

    return (
        <>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        className="w-full justify-between px-3 font-normal"
                        data-test={testId}
                    >
                        <span className="flex min-w-0 items-center gap-2">
                            {CurrentFlag && current ? (
                                <span className="flex h-4 w-6 shrink-0 overflow-hidden rounded-sm">
                                    <CurrentFlag title={current.label} />
                                </span>
                            ) : null}
                            <span
                                className={cn(
                                    'truncate',
                                    !current && 'text-muted-foreground',
                                )}
                            >
                                {current?.label ??
                                    t('organisation.country.placeholder')}
                            </span>
                        </span>
                        <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-(--radix-popover-trigger-width) min-w-72 p-0"
                    align="start"
                >
                    {/* « cote » doit trouver « Côte d'Ivoire » : le filtre par defaut de la liste
                        ne retire pas les accents. */}
                    <Command
                        filter={(item, search) =>
                            matchesSearch(item, search) ? 1 : 0
                        }
                    >
                        <CommandInput
                            placeholder={t('organisation.country.search')}
                        />
                        <CommandList>
                            <CommandEmpty>
                                {t('organisation.country.empty')}
                            </CommandEmpty>
                            <CommandGroup>
                                {countries.map((country) => {
                                    const Flag = flags[country.code];

                                    return (
                                        <CommandItem
                                            key={country.code}
                                            value={`${country.label} ${country.code}`}
                                            onSelect={() => {
                                                chosen.current = true;
                                                setValue(country.code);
                                                setOpen(false);
                                            }}
                                            className="min-h-11 gap-2"
                                        >
                                            <span className="flex h-4 w-6 shrink-0 overflow-hidden rounded-sm">
                                                {Flag ? (
                                                    <Flag
                                                        title={country.label}
                                                    />
                                                ) : null}
                                            </span>
                                            <span className="flex-1 truncate">
                                                {country.label}
                                            </span>
                                            <Check
                                                className={cn(
                                                    'size-4',
                                                    country.code === value
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
            <input ref={hiddenInput} type="hidden" name={name} value={value} />
        </>
    );
}
