import { router } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { Lock } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { useTranslation } from '@/hooks/use-translation';
import type { UnlockOutcome } from '@/hooks/use-scan-lock';
import { clearScanStorage } from '@/lib/scan-queue';
import { logout } from '@/routes';

type Props = {
    attemptsLeft: number;
    online: boolean;
    onUnlock: (pin: string) => Promise<UnlockOutcome>;
};

const PinLength = 4;

/**
 * L'ecran de scan verrouille (SECURITY.md M8) : il couvre toute la page, la camera ne traite plus
 * aucun billet. Quatre chiffres le levent. Apres 5 essais rates, les donnees de scan de l'appareil
 * sont effacees et la session fermee : il faut se reconnecter avec email et mot de passe. Sans
 * reseau, la deconnexion attend son retour, l'ecran reste bloque en attendant.
 */
export function ScanLockOverlay({ attemptsLeft, online, onUnlock }: Props) {
    const { t } = useTranslation();
    const [pin, setPin] = useState('');
    const [checking, setChecking] = useState(false);
    const [wrong, setWrong] = useState(false);
    const exhausted = attemptsLeft === 0;

    useEffect(() => {
        if (!exhausted) {
            return;
        }

        clearScanStorage();

        if (online) {
            router.post(logout().url);
        }
    }, [exhausted, online]);

    const submit = async (value: string) => {
        setChecking(true);
        const outcome = await onUnlock(value);
        setChecking(false);
        setPin('');
        setWrong(outcome === 'wrong');
    };

    return (
        <div
            className="bg-background/95 fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="scan-lock-title"
            data-test="scan-lock"
        >
            <div className="w-full max-w-xs space-y-5 text-center">
                <Lock className="mx-auto size-8" />
                <div className="space-y-1">
                    <h2 id="scan-lock-title" className="text-lg font-semibold">
                        {t('scan.lock.title')}
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {exhausted
                            ? t(
                                  online
                                      ? 'scan.lock.exhausted'
                                      : 'scan.lock.exhausted_offline',
                              )
                            : t('scan.lock.description')}
                    </p>
                </div>

                {exhausted ? null : (
                    <>
                        <div className="flex justify-center">
                            <InputOTP
                                maxLength={PinLength}
                                value={pin}
                                onChange={(value) => {
                                    setPin(value);

                                    if (value.length === PinLength) {
                                        void submit(value);
                                    }
                                }}
                                pattern={REGEXP_ONLY_DIGITS}
                                inputMode="numeric"
                                autoComplete="off"
                                disabled={checking}
                                autoFocus
                                aria-label={t('scan.lock.pin_label')}
                            >
                                <InputOTPGroup>
                                    {Array.from(
                                        { length: PinLength },
                                        (_, index) => (
                                            <InputOTPSlot
                                                key={index}
                                                index={index}
                                            />
                                        ),
                                    )}
                                </InputOTPGroup>
                            </InputOTP>
                        </div>
                        <p
                            className="text-muted-foreground text-xs"
                            role="status"
                        >
                            {checking
                                ? t('scan.lock.checking')
                                : wrong
                                  ? t('scan.lock.wrong', {
                                        count: attemptsLeft,
                                    })
                                  : null}
                        </p>
                    </>
                )}
            </div>
        </div>
    );
}
