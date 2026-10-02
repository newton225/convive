import { AccountActions } from '@/components/console/account-actions';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format-date';
import type { ConsoleAccount } from '@/types';

type Props = {
    accounts: ConsoleAccount[];
};

/**
 * Une liste de comptes dans la console : qui est la personne, a quelles organisations elle
 * appartient, si sa double authentification est active, si le compte est bloque et pourquoi.
 */
export function AccountList({ accounts }: Props) {
    const { t, locale } = useTranslation();

    return (
        <ul className="divide-border divide-y">
            {accounts.map((account) => (
                <li
                    key={account.id}
                    className="flex flex-wrap items-start justify-between gap-3 py-3 text-sm"
                    data-test="console-account"
                >
                    <div className="min-w-0 flex-1 space-y-1">
                        <p className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">{account.name}</span>
                            {account.blockedAt ? (
                                <Badge variant="destructive">
                                    {t('console.accounts.blocked_badge')}
                                </Badge>
                            ) : null}
                            <Badge variant="outline">
                                {account.hasTwoFactor
                                    ? t('console.accounts.two_factor_on')
                                    : t('console.accounts.two_factor_off')}
                            </Badge>
                        </p>
                        <p className="text-muted-foreground break-all">
                            {account.email}
                            {account.phone ? ` · ${account.phone}` : ''}
                        </p>
                        <p className="text-muted-foreground">
                            {account.createdAt
                                ? t('console.accounts.created', {
                                      date: formatDate(
                                          account.createdAt,
                                          locale,
                                      ),
                                  })
                                : ''}
                            {account.organisations.length > 0
                                ? ` · ${account.organisations.join(', ')}`
                                : ''}
                        </p>
                        {account.blockedAt ? (
                            <p>
                                {t('console.accounts.blocked_since', {
                                    date: formatDate(account.blockedAt, locale),
                                })}{' '}
                                {account.blockedReason}
                            </p>
                        ) : null}
                    </div>
                    <AccountActions account={account} />
                </li>
            ))}
        </ul>
    );
}
