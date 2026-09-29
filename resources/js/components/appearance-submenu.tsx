import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, SunMoon, Sun } from 'lucide-react';
import {
    DropdownMenuPortal,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/hooks/use-translation';

const Modes: { value: Appearance; icon: LucideIcon }[] = [
    { value: 'light', icon: Sun },
    { value: 'dark', icon: Moon },
    { value: 'system', icon: Monitor },
];

const isAppearance = (value: string): value is Appearance =>
    Modes.some((mode) => mode.value === value);

/**
 * Le choix du theme dans le menu du compte : une entree « Apparence » qui deroule un sous-menu
 * (clair, sombre, systeme). Des elements radio, parcourables au clavier. Choisir ne referme pas
 * le menu : le nouveau theme se voit se propager depuis l'element touche (voir `use-appearance`).
 */
export function AppearanceSubmenu() {
    const { appearance, updateAppearance } = useAppearance();
    const { t } = useTranslation();

    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger
                className="cursor-pointer"
                data-test="appearance-submenu"
            >
                <SunMoon className="mr-2" />
                {t('navigation.appearance')}
            </DropdownMenuSubTrigger>
            <DropdownMenuPortal>
                <DropdownMenuSubContent className="w-40">
                    <DropdownMenuRadioGroup
                        value={appearance}
                        onValueChange={(value) => {
                            if (isAppearance(value)) {
                                updateAppearance(value);
                            }
                        }}
                    >
                        {Modes.map(({ value, icon: Icon }) => (
                            <DropdownMenuRadioItem
                                key={value}
                                value={value}
                                className="cursor-pointer"
                                onSelect={(event) => event.preventDefault()}
                                data-test={`appearance-${value}`}
                            >
                                <Icon className="mr-2" />
                                {t(`account.appearance_modes.${value}`)}
                            </DropdownMenuRadioItem>
                        ))}
                    </DropdownMenuRadioGroup>
                </DropdownMenuSubContent>
            </DropdownMenuPortal>
        </DropdownMenuSub>
    );
}
