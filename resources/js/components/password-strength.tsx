import { Check, X } from 'lucide-react';
import { Progress } from '@/components/ui/progress';
import { useTranslation } from '@/hooks/use-translation';
import type { PasswordPolicy } from '@/lib/password-strength';
import { passwordChecks, passwordScore } from '@/lib/password-strength';
import { cn } from '@/lib/utils';

// La couleur suit la solidite : rouge, orange, vert. Le mot (« Faible », « Moyen », « Fort ») dit
// la meme chose, pour qui ne distingue pas les couleurs.
const LevelBar = {
    weak: 'bg-red-500/20 [&>[data-slot=progress-indicator]]:bg-red-500',
    medium: 'bg-amber-500/20 [&>[data-slot=progress-indicator]]:bg-amber-500',
    strong: 'bg-emerald-600/20 [&>[data-slot=progress-indicator]]:bg-emerald-600',
} as const;

const LevelText = {
    weak: 'text-red-600 dark:text-red-400',
    medium: 'text-amber-600 dark:text-amber-400',
    strong: 'text-emerald-700 dark:text-emerald-400',
} as const;

/**
 * Indicateur de mot de passe (decision du proprietaire du projet, 2026-10-04) : la liste des regles
 * du serveur, cochees pendant la saisie, et une barre qui se remplit. Il dit exactement ce que le
 * serveur exige ; le serveur revalide de toute facon.
 */
export function PasswordStrength({
    password,
    policy,
}: {
    password: string;
    policy: PasswordPolicy;
}) {
    const { t } = useTranslation();
    const checks = passwordChecks(password, policy);
    const score = passwordScore(checks);
    const level = score === 1 ? 'strong' : score >= 0.5 ? 'medium' : 'weak';

    return (
        <div className="space-y-2" data-test="password-strength">
            <div className="flex items-center gap-3">
                <Progress
                    value={score * 100}
                    className={cn('h-1.5', LevelBar[level])}
                    aria-label={t('account.password_strength.label')}
                />
                <span
                    className={cn(
                        'shrink-0 text-xs font-medium',
                        LevelText[level],
                    )}
                >
                    {password === ''
                        ? ''
                        : t(`account.password_strength.levels.${level}`)}
                </span>
            </div>

            <ul className="space-y-1 text-xs">
                {checks.map((check) => (
                    <li
                        key={check.key}
                        className={cn(
                            'flex items-center gap-1.5',
                            check.met
                                ? 'text-foreground'
                                : 'text-muted-foreground',
                        )}
                        data-test={`password-rule-${check.key}`}
                        data-met={check.met}
                    >
                        {check.met ? (
                            <Check className="size-3.5" aria-hidden />
                        ) : (
                            <X className="size-3.5" aria-hidden />
                        )}
                        <span>
                            {t(`account.password_strength.rules.${check.key}`, {
                                min: policy.min,
                            })}
                            <span className="sr-only">
                                {' '}
                                {t(
                                    check.met
                                        ? 'account.password_strength.met'
                                        : 'account.password_strength.missing',
                                )}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>

            {policy.uncompromised ? (
                <p className="text-muted-foreground text-xs">
                    {t('account.password_strength.uncompromised')}
                </p>
            ) : null}
        </div>
    );
}
