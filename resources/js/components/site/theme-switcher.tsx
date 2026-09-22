import { Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/hooks/use-translation';

const Modes = [
    { value: 'light', icon: Sun },
    { value: 'dark', icon: Moon },
    { value: 'system', icon: Monitor },
] as const satisfies readonly { value: Appearance; icon: typeof Sun }[];

/**
 * Le choix du theme sur la vitrine : clair, sombre, ou celui du systeme. Reutilise le meme
 * mecanisme que les reglages (`useAppearance`), donc le choix est memorise et suit le visiteur
 * jusque dans le back-office. L'icone du bouton montre le theme reellement affiche.
 */
export function ThemeSwitcher() {
    const { t } = useTranslation();
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();

    const CurrentIcon = resolvedAppearance === 'dark' ? Moon : Sun;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="hover:bg-white/10 hover:text-white"
                    aria-label={t('site.theme.label')}
                    data-test="site-theme-switcher"
                >
                    <CurrentIcon className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40">
                {Modes.map(({ value, icon: Icon }) => (
                    <DropdownMenuItem
                        key={value}
                        className="cursor-pointer gap-2"
                        aria-checked={appearance === value}
                        data-test={`site-theme-${value}`}
                        onSelect={() => updateAppearance(value)}
                    >
                        <Icon className="size-4" />
                        {t(`account.appearance_modes.${value}`)}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
