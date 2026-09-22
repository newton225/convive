import { router } from '@inertiajs/react';
import { Check, Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/locale';
import type { LocaleCode } from '@/types';

export default function LocaleSwitcher() {
    const { t, locale, supportedLocales } = useTranslation();

    const switchLocale = (next: LocaleCode) => {
        if (next === locale) {
            return;
        }

        router.visit(update(), {
            method: 'put',
            data: { locale: next },
            preserveScroll: true,
        });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    data-test="locale-switcher-trigger"
                    aria-label={t('common.language.switch')}
                >
                    <Languages className="size-4" />
                    <span className="text-xs uppercase">{locale}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40">
                {Object.entries(supportedLocales).map(([code, label]) => (
                    <DropdownMenuItem
                        key={code}
                        data-test="locale-switcher-item"
                        className="cursor-pointer gap-2"
                        onSelect={() => switchLocale(code as LocaleCode)}
                    >
                        {label}
                        {code === locale && (
                            <Check className="ml-auto size-4" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
