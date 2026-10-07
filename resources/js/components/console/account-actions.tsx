import { router, useForm } from '@inertiajs/react';
import { KeyRound, Lock, LockOpen } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { block, twoFactorReset, unblock } from '@/routes/console/accounts';
import type { ConsoleAccount } from '@/types';

type Props = {
    account: ConsoleAccount;
};

type Pending = 'unblock' | 'reset';

/**
 * Les gestes de l'equipe Convive sur un compte (README section 3) : bloquer avec un motif,
 * debloquer, reinitialiser la double authentification. Chacun dit sa consequence avant d'agir ;
 * aucun ne donne acces au compte.
 */
export function AccountActions({ account }: Props) {
    const { t } = useTranslation();
    const [blocking, setBlocking] = useState(false);
    const [pending, setPending] = useState<Pending | null>(null);
    const [processing, setProcessing] = useState(false);
    const form = useForm({ reason: '' });

    const submitBlock = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(block(account.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setBlocking(false);
            },
        });
    };

    const run = () => {
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        };

        if (pending === 'unblock') {
            router.delete(unblock(account.id).url, options);
        }

        if (pending === 'reset') {
            router.post(twoFactorReset(account.id).url, {}, options);
        }
    };

    return (
        <div className="flex flex-wrap gap-2">
            {account.blockedAt ? (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setPending('unblock')}
                    data-test="console-account-unblock"
                >
                    <LockOpen />
                    {t('console.accounts.actions.unblock')}
                </Button>
            ) : (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setBlocking(true)}
                    data-test="console-account-block"
                >
                    <Lock />
                    {t('console.accounts.actions.block')}
                </Button>
            )}

            {account.hasTwoFactor ? (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setPending('reset')}
                    data-test="console-account-reset-two-factor"
                >
                    <KeyRound />
                    {t('console.accounts.actions.reset')}
                </Button>
            ) : null}

            <Dialog open={blocking} onOpenChange={setBlocking}>
                <DialogContent>
                    <form onSubmit={submitBlock} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>
                                {t('console.accounts.dialogs.block_title', {
                                    name: account.name,
                                })}
                            </DialogTitle>
                            <DialogDescription>
                                {t('console.accounts.dialogs.block')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-2">
                            <Label
                                htmlFor={`block-reason-${account.id}`}
                                required
                            >
                                {t('console.accounts.fields.reason')}
                            </Label>
                            <Textarea
                                id={`block-reason-${account.id}`}
                                name="reason"
                                required
                                rows={3}
                                maxLength={500}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                            <InputError message={form.errors.reason} />
                        </div>

                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    {t('common.actions.cancel')}
                                </Button>
                            </DialogClose>
                            <SubmitButton
                                variant="destructive"
                                processing={form.processing}
                                data-test="console-account-block-confirm"
                            >
                                {t('console.accounts.actions.block')}
                            </SubmitButton>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmActionDialog
                open={pending !== null}
                onOpenChange={(open) => !open && setPending(null)}
                title={t(
                    `console.accounts.dialogs.${pending ?? 'unblock'}_title`,
                    { name: account.name },
                )}
                description={t(
                    `console.accounts.dialogs.${pending ?? 'unblock'}`,
                )}
                confirmLabel={t(
                    `console.accounts.actions.${pending ?? 'unblock'}`,
                )}
                onConfirm={run}
                processing={processing}
                testId="console-account-confirm"
            />
        </div>
    );
}
