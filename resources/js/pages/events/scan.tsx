import { Head, router } from '@inertiajs/react';
import { BrowserQRCodeReader, type IScannerControls } from '@zxing/browser';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SubmitButton } from '@/components/submit-button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InstallPrompt } from '@/components/install-prompt';
import {
    OfflineScanPanel,
    type LocalScanKind,
} from '@/components/scan/offline-scan-panel';
import { RotateTicketKeyCard } from '@/components/scan/rotate-ticket-key-card';
import { ScanLockOverlay } from '@/components/scan/scan-lock-overlay';
import { ScanPinForm } from '@/components/scan/scan-pin-form';
import { useOnlineStatus } from '@/hooks/use-online-status';
import { useScanLock } from '@/hooks/use-scan-lock';
import { useScanQueue, type SyncOutcome } from '@/hooks/use-scan-queue';
import { translate, useTranslation } from '@/hooks/use-translation';
import {
    clearEventScanStorage,
    hasSeenLocally,
    markSeenLocally,
    readStoredRevocationList,
    storeRevocationList,
} from '@/lib/scan-queue';
import {
    verifyRevocationList,
    verifyTicketOffline,
    type ExpectedEvent,
} from '@/lib/ticket-verifier';
import { formatDateTime } from '@/lib/format-date';
import type { ScanPinVerifier } from '@/lib/scan-pin';
import { readStation, writeStation } from '@/lib/scan-station';
import { can, Permission } from '@/lib/permissions';
import { index as eventsIndex } from '@/routes/tenants/events';
import { index, verify } from '@/routes/tenants/events/scan';
import type {
    ScanEventProps,
    ScanOutcome,
    ScanRecentRow,
    TenantPermissions,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string };
    tenantId: number;
    event: ScanEventProps;
    revocationList: string | null;
    canRotateKey: boolean;
    scanPin: ScanPinVerifier | null;
    permissions: TenantPermissions;
    recent: ScanRecentRow[];
    acceptedCount: number;
    expectedCount: number;
    result?: ScanOutcome;
};

// Lit le verdict du serveur dans les props d'une reponse, ou `failed` quand il n'y en a pas.
const outcomeOf = (props: unknown): SyncOutcome => {
    if (typeof props === 'object' && props !== null && 'result' in props) {
        const outcome = props.result;

        if (
            typeof outcome === 'object' &&
            outcome !== null &&
            'result' in outcome
        ) {
            const value = outcome.result;

            if (
                value === 'accepted' ||
                value === 'already_scanned' ||
                value === 'refused'
            ) {
                return value;
            }
        }
    }

    return 'failed';
};

/**
 * README ecran 26 : le controle a l'entree, etape 7 de « Ordre de construction ». Une visite
 * Inertia par code lu (rechargement partiel, `only`), pas une navigation complete : la camera
 * reste active d'un scan a l'autre.
 *
 * Hors ligne (CLAUDE.md, pile PWA) : le billet est verifie sur l'appareil avec la cle publique de
 * l'evenement (`verifyTicketOffline`), le passage est mis en file locale puis rejoue un par un au
 * retour du reseau. Le serveur reste juge : un billet accepte hors ligne qu'il refuse est signale
 * « a revoir ». Limite connue : l'heure enregistree est celle de la synchronisation, pas du scan.
 */
export default function EventScan({
    tenant,
    tenantId,
    event,
    revocationList,
    canRotateKey,
    scanPin,
    permissions,
    recent,
    acceptedCount,
    expectedCount,
    result,
}: Props) {
    const { t, locale } = useTranslation();
    const videoRef = useRef<HTMLVideoElement>(null);
    const controlsRef = useRef<IScannerControls | null>(null);
    const lastTokenRef = useRef<string | null>(null);
    const [cameraError, setCameraError] = useState(false);
    const [pending, setPending] = useState(false);
    const [station, setStation] = useState(readStation);
    // Le resultat reste affiche jusqu'a « Scanner suivant » (prototype) ou jusqu'au scan suivant.
    const [resultDismissed, setResultDismissed] = useState(false);
    const canForce = can(permissions, Permission.ScanForce);
    const online = useOnlineStatus();
    const lock = useScanLock(scanPin);
    // La camera tourne en continu : sans code choisi ou ecran verrouille, les billets lus sont
    // ignores (SECURITY.md M8). Des refs, parce que le lecteur est installe une seule fois.
    const scanBlockedRef = useRef(scanPin === null || lock.locked);
    const registerActivityRef = useRef(lock.registerActivity);

    useEffect(() => {
        scanBlockedRef.current = scanPin === null || lock.locked;
        registerActivityRef.current = lock.registerActivity;
    }, [scanPin, lock.locked, lock.registerActivity]);
    const [localResult, setLocalResult] = useState<LocalScanKind | null>(null);
    const expected: ExpectedEvent = {
        tenantId,
        eventId: event.id,
        keyVersion: event.qrKeyVersion,
        validUntil: event.ticketValidUntil,
    };

    // Garde la derniere liste de revocation authentique recue (SECURITY.md C2) : c'est elle que
    // le scan hors ligne consultera jusqu'a la prochaine synchronisation.
    useEffect(() => {
        void verifyRevocationList(
            revocationList,
            event.qrPublicKey,
            expected,
        ).then((revoked) => {
            if (revoked !== null && revocationList !== null) {
                storeRevocationList(event.id, revocationList);
            }
        });
        // `expected` est derive des props deja listees.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [revocationList, event.qrPublicKey, event.qrKeyVersion]);

    // Rejoue un scan de la file aupres du serveur et rend son verdict ; tout echec le laisse en file.
    const syncOne = (token: string) =>
        new Promise<SyncOutcome>((resolve) => {
            router.post(
                verify([tenant.slug, event.id]).url,
                { token, force: false, station },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['result', 'recent', 'acceptedCount'],
                    onSuccess: (page) => resolve(outcomeOf(page.props)),
                    onError: () => resolve('failed'),
                    onCancel: () => resolve('failed'),
                },
            );
        });

    const { queued, syncing, review, enqueue, sync, dismissReview } =
        useScanQueue(event.id, syncOne);

    useEffect(() => {
        if (event.closed) {
            clearEventScanStorage(event.id);
        }
    }, [event.closed, event.id]);

    const wasOnlineRef = useRef(online);

    useEffect(() => {
        if (online && !wasOnlineRef.current) {
            // Retour du reseau : nouvelle cle et liste de revocation a jour. Visite asynchrone,
            // pour ne pas annuler les envois de la file rejoues en meme temps.
            router.reload({ only: ['event', 'revocationList'], async: true });
        }

        wasOnlineRef.current = online;

        if (online && queued > 0) {
            void sync();
        }
        // Au retour du reseau seulement : `sync` et `queued` changent a chaque scan.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [online]);

    async function scanOffline(token: string) {
        lastTokenRef.current = token;
        setTimeout(() => {
            if (lastTokenRef.current === token) {
                lastTokenRef.current = null;
            }
        }, 3000);

        const revoked =
            (await verifyRevocationList(
                readStoredRevocationList(event.id),
                event.qrPublicKey,
                expected,
            )) ?? [];

        const verification = await verifyTicketOffline(
            token,
            event.qrPublicKey,
            expected,
            revoked,
        );

        if (verification.status !== 'valid') {
            setLocalResult(verification.status);

            return;
        }

        if (hasSeenLocally(event.id, verification.registrationId)) {
            setLocalResult('already_local');

            return;
        }

        markSeenLocally(event.id, verification.registrationId);
        enqueue(token);
        setLocalResult('verified');
    }

    function handleToken(token: string) {
        if (online) {
            setLocalResult(null);
            submit(token);

            return;
        }

        void scanOffline(token);
    }

    function submit(token: string, force = false) {
        setPending(true);
        lastTokenRef.current = token;

        router.post(
            verify([tenant.slug, event.id]).url,
            { token, force, station },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['result', 'recent', 'acceptedCount'],
                onSuccess: () => setResultDismissed(false),
                onFinish: () => {
                    setPending(false);
                    // Laisse la meme presentation valoir « deja scanne » pendant un court
                    // instant plutot que de la resoumettre en boucle tant qu'elle reste dans
                    // le champ de la camera.
                    setTimeout(() => {
                        if (lastTokenRef.current === token) {
                            lastTokenRef.current = null;
                        }
                    }, 3000);
                },
            },
        );
    }

    useEffect(() => {
        let cancelled = false;
        const reader = new BrowserQRCodeReader();

        reader
            .decodeFromVideoDevice(
                undefined,
                videoRef.current ?? undefined,
                (decoded) => {
                    if (cancelled || !decoded || scanBlockedRef.current) {
                        return;
                    }

                    registerActivityRef.current();

                    const text = decoded.getText();

                    if (text === lastTokenRef.current) {
                        return;
                    }

                    handleToken(text);
                },
            )
            .then((controls) => {
                controlsRef.current = controls;
            })
            .catch(() => setCameraError(true));

        return () => {
            cancelled = true;
            controlsRef.current?.stop();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <>
            <Head title={t('scan.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('scan.title')}
                    description={event.name}
                />

                <InstallPrompt />

                {scanPin === null ? (
                    <Card data-test="scan-pin-setup">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('scan.pin_setup.title')}
                            </CardTitle>
                            <p className="text-muted-foreground text-sm">
                                {t('scan.pin_setup.description')}
                            </p>
                        </CardHeader>
                        <CardContent>
                            <ScanPinForm
                                submitLabel={t('account.scan_pin.create')}
                            />
                        </CardContent>
                    </Card>
                ) : null}

                {lock.locked ? (
                    <ScanLockOverlay
                        attemptsLeft={lock.attemptsLeft}
                        online={online}
                        onUnlock={lock.unlock}
                    />
                ) : null}

                <div className="grid gap-6 lg:grid-cols-[320px_1fr]">
                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <div className="bg-muted aspect-square overflow-hidden rounded-lg">
                                <video
                                    ref={videoRef}
                                    className="h-full w-full object-cover"
                                    data-test="scan-video"
                                    muted
                                    playsInline
                                />
                            </div>

                            {cameraError ? (
                                <p
                                    className="text-destructive text-center text-sm"
                                    data-test="scan-camera-error"
                                >
                                    {t('scan.viewfinder.camera_denied')}
                                </p>
                            ) : (
                                <p className="text-muted-foreground text-center text-sm">
                                    {pending
                                        ? t('scan.viewfinder.scanning')
                                        : t('scan.viewfinder.placeholder')}
                                </p>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="scan-station">
                                    {t('scan.station.label')}
                                </Label>
                                <Input
                                    id="scan-station"
                                    name="station"
                                    value={station}
                                    maxLength={60}
                                    placeholder={t('scan.station.placeholder')}
                                    onChange={(changeEvent) => {
                                        setStation(changeEvent.target.value);
                                        writeStation(changeEvent.target.value);
                                    }}
                                    data-test="scan-station"
                                />
                            </div>

                            <div className="text-center">
                                <p
                                    className="text-3xl font-semibold tabular-nums"
                                    data-test="scan-counter"
                                >
                                    {t('scan.counter.value', {
                                        entered: acceptedCount,
                                        expected: expectedCount,
                                    })}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {t('scan.counter.label')}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <OfflineScanPanel
                            online={online}
                            queued={queued}
                            syncing={syncing}
                            review={review}
                            localResult={localResult}
                            onSync={() => void sync()}
                            onDismissReview={dismissReview}
                        />

                        {result && !resultDismissed ? (
                            <Card
                                data-test="scan-result"
                                data-result={result.result}
                            >
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        {t(`scan.results.${result.result}`)}
                                        {result.forced ? (
                                            <Badge variant="secondary">
                                                {t('scan.result.forced_badge')}
                                            </Badge>
                                        ) : null}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {result.registration ? (
                                        <>
                                            <p
                                                className="font-medium"
                                                data-test="scan-result-name"
                                            >
                                                {result.registration.name}
                                            </p>
                                            <p className="text-muted-foreground text-sm">
                                                {result.registration.unit} ·{' '}
                                                {result.registration.partySize}
                                            </p>
                                            <p className="text-sm">
                                                {result.registration
                                                    .tableNumber !== null
                                                    ? t('scan.result.table', {
                                                          number: String(
                                                              result
                                                                  .registration
                                                                  .tableNumber,
                                                          ),
                                                      })
                                                    : t('scan.result.no_table')}
                                            </p>
                                        </>
                                    ) : null}

                                    {result.result === 'already_scanned' ? (
                                        <div className="space-y-2">
                                            {result.firstScannedAt ? (
                                                <p className="text-muted-foreground text-sm">
                                                    {t(
                                                        'scan.result.first_scanned_at',
                                                        {
                                                            time: formatDateTime(
                                                                result.firstScannedAt,
                                                                locale,
                                                            ),
                                                        },
                                                    )}
                                                </p>
                                            ) : null}
                                            {result.firstScannedBy ? (
                                                <p className="text-muted-foreground text-sm">
                                                    {t(
                                                        'scan.result.first_scanned_by',
                                                        {
                                                            name: result.firstScannedBy,
                                                        },
                                                    )}
                                                </p>
                                            ) : null}
                                            {canForce && !result.forced ? (
                                                <SubmitButton
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    data-test="scan-force"
                                                    processing={pending}
                                                    onClick={() =>
                                                        lastTokenRef.current &&
                                                        submit(
                                                            lastTokenRef.current,
                                                            true,
                                                        )
                                                    }
                                                >
                                                    {t('scan.result.force')}
                                                </SubmitButton>
                                            ) : null}
                                        </div>
                                    ) : null}

                                    {result.result === 'refused' ? (
                                        <p className="text-muted-foreground text-sm">
                                            {t('scan.result.refused_help')}
                                        </p>
                                    ) : null}

                                    <Button
                                        variant="outline"
                                        size="sm"
                                        data-test="scan-next"
                                        onClick={() => {
                                            setResultDismissed(true);
                                            lastTokenRef.current = null;
                                        }}
                                    >
                                        {t('scan.result.next')}
                                    </Button>
                                </CardContent>
                            </Card>
                        ) : null}

                        {canRotateKey && event.qrPublicKey !== null ? (
                            <RotateTicketKeyCard
                                tenantSlug={tenant.slug}
                                eventId={event.id}
                                keyVersion={event.qrKeyVersion}
                            />
                        ) : null}

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('scan.recent.title')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {recent.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">
                                        {t('scan.recent.empty')}
                                    </p>
                                ) : (
                                    <div className="divide-y">
                                        {recent.map((row) => (
                                            <div
                                                key={row.id}
                                                className="flex items-center justify-between gap-2 py-2 text-sm"
                                                data-test="scan-recent-row"
                                            >
                                                <span>
                                                    {row.name ?? '-'}
                                                    {row.station ? (
                                                        <span className="text-muted-foreground block text-xs">
                                                            {row.station}
                                                        </span>
                                                    ) : null}
                                                </span>
                                                <Badge
                                                    variant={
                                                        row.result ===
                                                        'accepted'
                                                            ? 'default'
                                                            : row.result ===
                                                                'refused'
                                                              ? 'destructive'
                                                              : 'secondary'
                                                    }
                                                >
                                                    {t(
                                                        `scan.results.${row.result}`,
                                                    )}
                                                </Badge>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

EventScan.layout = (props: {
    tenant: { slug: string };
    event: { id: number };
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'events.title'),
            href: eventsIndex(props.tenant.slug),
        },
        {
            title: translate(props.translations, 'scan.title'),
            href: index([props.tenant.slug, props.event.id]),
        },
    ],
});
