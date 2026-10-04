<?php

namespace App\Http\Controllers\Public;

use App\Actions\Registrations\CreateRegistration;
use App\Actions\Registrations\HoldRegistration;
use App\Actions\Registrations\PhoneVerification;
use App\Actions\Waitlist\PromoteNextWaitlistEntry;
use App\Enums\BrandFile;
use App\Enums\PhoneCodeResult;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreRegistrationRequest;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\Unit;
use App\Support\BotCheck;
use App\Support\LegalDocument;
use App\Support\OngoingReservation;
use App\Support\PhoneNumber;
use App\Support\PlanLimits;
use App\Support\TicketCard;
use App\Support\TicketQrCode;
use App\Support\VisitorCountry;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le formulaire d'inscription (README ecran 4) et sa reservation (ecran 5, etape 5 de
 * « Ordre de construction ») : nom, telephone, unite, accompagnateurs, montant recalcule en
 * direct, puis decompte de reservation des la disponibilite verifiee.
 *
 * Meme absence d'authentification et meme cloisonnement par sous-domaine que
 * `Public\EventController` (voir CLAUDE.md, « Multi-locataire »).
 */
class RegistrationController extends Controller
{
    /**
     * Show the registration form for the given event's public link.
     */
    public function create(string $token): Response|RedirectResponse
    {
        $event = $this->publishedEvent($token);

        if (! $event->acceptsRegistrations()) {
            return redirect()->to($event->publicUrl());
        }

        $tenant = Tenant::current();

        // La copie de ce formulaire gardee par le navigateur est chiffree : l'inscription efface ces
        // copies (`clearHistory()` dans `store()`), et le bouton « retour » redemande alors la page au
        // serveur, avec la proposition de reprendre la reservation (mecanisme officiel d'Inertia).
        Inertia::encryptHistory();

        return Inertia::render('public/registration', [
            'token' => $token,
            // L'invite lit comment ses donnees sont traitees avant de les envoyer.
            'privacyUrl' => LegalDocument::url('privacy'),
            'defaultCountry' => VisitorCountry::from(request()),
            // La cle publique du widget anti-robot quand l'evenement le demande, sinon rien a afficher.
            'botCheckSiteKey' => BotCheck::appliesTo($event) ? BotCheck::siteKey() : null,
            // L'invite revenu ici par le bouton « retour » reprend sa reservation en cours.
            'ongoingReservationUrl' => $this->ongoingReservationUrl($event, $token),
            'event' => [
                'name' => $event->name,
                'pricePerPerson' => $event->price_per_person,
                'companionLimit' => $event->companion_limit,
                'remainingSeats' => $event->publicRemainingSeats(),
            ],
            'tenant' => [
                'displayName' => $tenant->branding->display_name ?? $tenant->name,
                'colors' => $event->colors(),
                'logoUrl' => $tenant->branding?->brandFileUrl(BrandFile::Logo),
            ],
            'units' => Unit::active()->ordered()->get()->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
            ]),
        ]);
    }

    /**
     * Store a newly created registration and immediately attempt to hold its seats.
     *
     * README 2.2 : toute nouvelle inscription doit d'abord revérifier le stock avant de
     * demarrer le decompte. Un brouillon qui n'obtient pas sa place n'a aucune raison d'etre
     * conserve : il est supprime plutot que de laisser trainer un dossier fantome.
     */
    public function store(StoreRegistrationRequest $request, string $token): RedirectResponse
    {
        $event = $this->publishedEvent($token);

        if (! $event->acceptsRegistrations()) {
            return redirect()->to($event->publicUrl());
        }

        if (Registration::phoneHoldsSeats($event, (string) $request->validated('phone'))) {
            return back()->withInput()->withErrors(['phone' => __('guest.registration.errors.phone_already_active')]);
        }

        // Une inscription confirmee existe deja pour ce numero : la nouvelle n'est possible que comme
        // inscription additionnelle, apres que l'invite l'a confirme.
        if (! $request->boolean('confirm_additional') && Registration::phoneHasConfirmed($event, (string) $request->validated('phone'))) {
            return back()->withInput()->withErrors(['additional_registration' => __('guest.registration.errors.already_confirmed')]);
        }

        if (($waitUntil = Registration::phoneBackoffUntil($event, (string) $request->validated('phone'))) !== null) {
            return back()->withInput()->withErrors(['phone' => $this->backoffMessage($waitUntil)]);
        }

        // Verification du telephone exigee par l'evenement (SECURITY.md C3), pour un numero ivoirien
        // seulement (`PhoneVerification::appliesTo()`).
        $verifyPhone = $event->rule_phone_verification && PhoneVerification::appliesTo((string) $request->validated('phone'));

        // Plafond de codes du numero atteint : le dire tout de suite, plutot que de laisser l'invite
        // attendre un SMS qui ne partira pas.
        if ($verifyPhone && PhoneVerification::exhausted((string) $request->validated('phone'))) {
            return back()->withInput()->withErrors(['phone' => __('guest.registration.errors.too_many_codes')]);
        }

        $created = app(CreateRegistration::class)->handle($event, [
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'unit_id' => (int) $request->validated('unit_id'),
            'companions' => $request->companions(),
        ]);

        $registration = $created['registration'];
        OngoingReservation::remember($event, $created['resumeToken']);
        Inertia::clearHistory();

        // Le brouillon ne bloque aucune place tant que le code n'est pas saisi.
        if ($verifyPhone) {
            if (! app(PhoneVerification::class)->send($registration)) {
                $registration->delete();

                return back()->withInput()->withErrors(['phone' => __('guest.registration.errors.too_many_codes')]);
            }

            return to_route('public.registrations.verify.show', [
                'token' => $token,
                'resume' => $created['resumeToken'],
            ]);
        }

        if (! app(HoldRegistration::class)->handle($event, $registration)) {
            $registration->delete();

            // Le plafond d'inscrits du plan de l'organisation (README section 3) refuse la
            // reservation comme un evenement complet ; l'invite, qui voit encore des places sur la
            // page de l'evenement, doit savoir pourquoi, sans jargon d'abonnement.
            if (! PlanLimits::for(Tenant::current())->canRegister($registration->party_size)) {
                return back()->withInput()->withErrors(['registration' => __('guest.registration.errors.registrations_paused')]);
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => __('guest.flash.no_seats_left')]);

            return redirect()->to($event->publicUrl());
        }

        return to_route('public.registrations.show', [
            'token' => $token,
            'resume' => $created['resumeToken'],
        ]);
    }

    /**
     * Show the code entry screen of the phone verification (SECURITY.md C3).
     */
    public function showVerification(string $token, string $resume): Response|RedirectResponse
    {
        $event = $this->publishedEvent($token);
        $registration = $this->registrationForResumeToken($event, $resume);

        $viaWhatsApp = PhoneVerification::viaWhatsApp();

        // Deja au-dela du brouillon, ou deja verifiee par code SMS : rien a faire ici. Verifiee par
        // WhatsApp, la page reste : elle continue d'elle-meme vers la reservation.
        if ($registration->status !== RegistrationStatus::Draft || ($registration->phone_verified_at !== null && ! $viaWhatsApp)) {
            return to_route('public.registrations.show', ['token' => $token, 'resume' => $resume]);
        }

        $tenant = Tenant::current();

        return Inertia::render('public/registration-verify', [
            'token' => $token,
            'resume' => $resume,
            'event' => ['name' => $event->name],
            'tenant' => [
                'displayName' => $tenant->branding->display_name ?? $tenant->name,
                'colors' => $event->colors(),
                'logoUrl' => $tenant->branding?->brandFileUrl(BrandFile::Logo),
            ],
            // Seuls les deux derniers chiffres : l'invite reconnait son numero, un tiers qui
            // tomberait sur la page n'apprend rien.
            'maskedPhone' => '•• •• •• '.substr((string) preg_replace('/\D+/', '', $registration->phone), -2),
            'codeMinutes' => PhoneVerification::CodeMinutes,
            'method' => $viaWhatsApp ? 'whatsapp' : 'sms',
            ...$this->whatsAppVerificationProps($registration, $viaWhatsApp),
        ]);
    }

    /**
     * The WhatsApp side of the verification page : le lien pret a envoyer, le code, et si le message
     * est deja arrive (la page interroge ce drapeau toutes les quelques secondes).
     *
     * @return array{whatsappLink: string|null, whatsappCode: string|null, whatsappNumber: string|null, whatsappVerified: bool}
     */
    private function whatsAppVerificationProps(Registration $registration, bool $viaWhatsApp): array
    {
        $check = $viaWhatsApp ? PhoneVerification::whatsAppCheck($registration) : null;

        return [
            'whatsappLink' => $check ? PhoneVerification::whatsAppLink($check->code) : null,
            'whatsappCode' => $check?->code,
            'whatsappNumber' => $viaWhatsApp ? PhoneNumber::display((string) config('services.whatsapp.inbound.number')) : null,
            'whatsappVerified' => $viaWhatsApp && $registration->phone_verified_at !== null,
        ];
    }

    /**
     * Continue once the guest's WhatsApp message has verified the phone : les places se retiennent
     * comme apres un code SMS.
     */
    public function completeWhatsApp(string $token, string $resume): RedirectResponse
    {
        $event = $this->publishedEvent($token);
        $registration = $this->registrationForResumeToken($event, $resume);

        if ($registration->status !== RegistrationStatus::Draft) {
            return to_route('public.registrations.show', ['token' => $token, 'resume' => $resume]);
        }

        if ($registration->phone_verified_at === null) {
            return back()->withErrors(['whatsapp' => __('guest.phone_verification.whatsapp_not_received')]);
        }

        return $this->holdVerified($event, $registration, $token, $resume);
    }

    /**
     * Check the code, then hold the seats exactly as an unverified registration would.
     */
    public function verify(Request $request, string $token, string $resume, PhoneVerification $verification): RedirectResponse
    {
        $event = $this->publishedEvent($token);
        $registration = $this->registrationForResumeToken($event, $resume);

        if ($registration->status !== RegistrationStatus::Draft) {
            return to_route('public.registrations.show', ['token' => $token, 'resume' => $resume]);
        }

        $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);

        $result = $verification->verify($registration, (string) $request->input('code'));

        if ($result !== PhoneCodeResult::Verified) {
            return back()->withErrors(['code' => $result->message()]);
        }

        return $this->holdVerified($event, $registration, $token, $resume);
    }

    /**
     * Hold the seats of a registration whose phone was just verified.
     */
    private function holdVerified(Event $event, Registration $registration, string $token, string $resume): RedirectResponse
    {
        // Les regles verifiees au formulaire se rejouent : le code a pu etre saisi longtemps
        // apres, une autre reservation du meme numero a pu etre prise entre-temps.
        if (! $event->acceptsRegistrations() || Registration::phoneHoldsSeats($event, $registration->phone, $registration->id)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('guest.registration.errors.phone_already_active')]);

            return redirect()->to($event->publicUrl());
        }

        if (! app(HoldRegistration::class)->handle($event, $registration)) {
            $registration->delete();

            Inertia::flash('toast', ['type' => 'error', 'message' => PlanLimits::for(Tenant::current())->canRegister($registration->party_size)
                ? __('guest.flash.no_seats_left')
                : __('guest.registration.errors.registrations_paused')]);

            return redirect()->to($event->publicUrl());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('guest.flash.phone_verified')]);

        return to_route('public.registrations.show', ['token' => $token, 'resume' => $resume]);
    }

    /**
     * Send a new code (the previous one stops working).
     */
    public function resendCode(string $token, string $resume, PhoneVerification $verification): RedirectResponse
    {
        $event = $this->publishedEvent($token);
        $registration = $this->registrationForResumeToken($event, $resume);

        if ($registration->status === RegistrationStatus::Draft && $registration->phone_verified_at === null) {
            Inertia::flash('toast', $verification->send($registration)
                ? ['type' => 'success', 'message' => __(PhoneVerification::viaWhatsApp() ? 'guest.flash.whatsapp_code_renewed' : 'guest.flash.code_resent')]
                : ['type' => 'error', 'message' => __('guest.registration.errors.too_many_codes')]);
        }

        return to_route('public.registrations.verify.show', ['token' => $token, 'resume' => $resume]);
    }

    /**
     * Show the reservation : countdown, payment accounts (README ecran 5).
     *
     * Adressee par un jeton de reprise, jamais par l'identifiant de l'inscription (CLAUDE.md,
     * « Securite ») : aucun identifiant sequentiel devinable dans une URL publique.
     */
    public function show(string $token, string $resume): Response|RedirectResponse
    {
        $event = $this->publishedEvent($token);
        $registration = $this->registrationForResumeToken($event, $resume);

        return $this->render($token, $event, $registration, $resume);
    }

    /**
     * Show a registration via the signed link sent by the invitation card and the reminders
     * (README 2.7), rather than the guest's own resume token.
     *
     * La signature se verifie ici, en `hash_equals` (comparaison a temps constant) : un lien
     * altere ou pour une autre inscription recoit le meme 404 qu'un jeton de reprise inconnu.
     */
    public function link(Request $request, string $token, Registration $registration): Response|RedirectResponse
    {
        $event = $this->publishedEvent($token);

        abort_unless($registration->event_id === $event->id, 404);
        abort_unless(
            hash_equals($registration->notificationToken(), (string) $request->query('signature')),
            404,
        );

        return $this->render($token, $event, $registration);
    }

    /**
     * Render the reservation or the billet for the given registration (README ecrans 5 to 7),
     * shared by the resume-token entry point and the signed-link one.
     *
     * `$resume` est absent pour l'entree par lien signe : le jeton de reprise n'est jamais
     * stocke en clair (CLAUDE.md, « Lien de retour vers l'inscription »), une tache planifiee
     * ne peut donc pas le reconstituer pour le lien qu'elle envoie. Relancer une reservation ou
     * deposer une preuve depuis ce lien reste hors de portee pour l'instant : la page masque ces
     * actions plutot que de construire une URL invalide (README 2.7, a completer separement par
     * un couple d'actions signees dediees, sur le meme principe que ce lien lui-meme).
     */
    private function render(string $token, Event $event, Registration $registration, ?string $resume = null): Response
    {
        // Relance paresseuse : le decompte peut avoir expire depuis la derniere lecture, sans
        // que la tache planifiee (README 2.4) ne soit encore passee. Le statut stocke doit deja
        // refleter la realite avant l'affichage, et la place qui se libere ainsi fait avancer
        // la liste d'attente (README 2.3) sans attendre le prochain passage planifie.
        if ($registration->holdHasExpired()) {
            $registration->update(['status' => RegistrationStatus::Expired]);
            app(PromoteNextWaitlistEntry::class)->handle($event);
        }

        $tenant = Tenant::current();

        return Inertia::render('public/registration-show', [
            'token' => $token,
            'resume' => $resume,
            'event' => [
                'name' => $event->name,
                // Pour le recapitulatif avant relance (README ecran 9) : le nombre de places
                // encore libres au moment ou l'invite regarde, informatif seulement. La relance
                // elle-meme revalide le stock (`HoldRegistration`), c'est elle qui tranche.
                // Places masquees par l'organisateur (le defaut) : seulement s'il en reste assez
                // pour ce groupe, jamais le chiffre.
                'remainingSeats' => $event->publicRemainingSeats(),
                'hasEnoughSeats' => $event->remainingSeats() >= $registration->party_size,
            ],
            'tenant' => [
                'displayName' => $tenant->branding->display_name ?? $tenant->name,
                'colors' => $event->colors(),
                'logoUrl' => $tenant->branding?->brandFileUrl(BrandFile::Logo),
            ],
            'registration' => [
                'reference' => $registration->reference,
                'name' => $registration->name,
                'unit' => $registration->unit->name,
                'amountDue' => $registration->amount_due,
                'status' => $registration->status->value,
                'heldUntil' => $registration->held_until?->toISOString(),
                'companions' => $registration->companions()->orderBy('position')->with('unit')->get()->map(
                    fn ($companion) => ['name' => $companion->name, 'unit' => $companion->unit->name],
                ),
                'ticket' => $registration->status === RegistrationStatus::Confirmed
                    ? $this->ticketSummary($registration, $event, $tenant)
                    : null,
            ],
            'paymentAccounts' => $event->paymentAccounts()
                ->publiclyVisible()
                ->ordered()
                ->get()
                ->map(fn (PaymentAccount $account) => [
                    'id' => $account->id,
                    'label' => $account->label,
                    'channelLabel' => $account->channel?->label(),
                    // Le canal de la preuve est celui du compte choisi, un pour un (decision du
                    // 2026-09-29) : c'est lui qui dit si une reference de transaction est attendue.
                    'requiresReference' => $account->channel?->hasAccountNumber() ?? true,
                    'accountNumber' => $account->account_number,
                    'holderName' => $account->holder_name,
                    'instructions' => $account->instructions,
                ]),
        ]);
    }

    /**
     * Retry a reservation whose countdown has expired, or whose proof was rejected : re-verify
     * the stock and restart the countdown, rather than let either become a dead end (README
     * 2.2). Un rejet ne redemarre pas le decompte de son propre chef (voir
     * `App\Actions\PaymentProofs\RejectPaymentProof`) : c'est ce meme mecanisme de relance qui
     * s'en charge, une fois l'invite revenu sur cette page.
     */
    public function retry(string $token, string $resume): RedirectResponse
    {
        $event = $this->publishedEvent($token);
        $registration = $this->registrationForResumeToken($event, $resume);

        abort_unless(
            in_array($registration->status, [
                RegistrationStatus::Held,
                RegistrationStatus::Expired,
                RegistrationStatus::ProofRejected,
            ], true),
            404,
        );

        if ($registration->holdHasExpired()) {
            $registration->update(['status' => RegistrationStatus::Expired]);
        }

        // Relancer une reservation expiree ne doit pas contourner « une reservation active par
        // numero » (SECURITY.md C3) : une autre inscription du meme telephone peut avoir ete prise
        // entre-temps.
        if (Registration::phoneHoldsSeats($event, $registration->phone, $registration->id)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('guest.registration.errors.phone_already_active')]);

            return to_route('public.registrations.show', ['token' => $token, 'resume' => $resume]);
        }

        if (($waitUntil = Registration::phoneBackoffUntil($event, $registration->phone)) !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $this->backoffMessage($waitUntil)]);

            return to_route('public.registrations.show', ['token' => $token, 'resume' => $resume]);
        }

        if (! app(HoldRegistration::class)->handle($event, $registration)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('guest.flash.no_seats_left')]);

            return redirect()->to($event->publicUrl());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('guest.flash.hold_restarted')]);

        return to_route('public.registrations.show', ['token' => $token, 'resume' => $resume]);
    }

    /**
     * Tell the guest how long to wait after repeated lapsed reservations (SECURITY.md C3), in
     * minutes rather than a clock time : the guest's device may not share the server's timezone.
     */
    private function backoffMessage(CarbonInterface $waitUntil): string
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($waitUntil) / 60));

        return trans_choice('guest.registration.errors.phone_backoff', $minutes, ['minutes' => $minutes]);
    }

    /**
     * Get the billet shown once the registration is confirmed (README ecran 7).
     *
     * Lecture seule : le billet est emis a la validation de la preuve
     * (`App\Actions\PaymentProofs\ValidatePaymentProof`), jamais ici. Une inscription confirmee
     * sans billet retrouve (ne devrait pas arriver en usage normal) affiche simplement une carte
     * absente plutot que d'en creer un depuis une requete de lecture.
     *
     * @return array<string, mixed>|null
     */
    private function ticketSummary(Registration $registration, Event $event, Tenant $tenant): ?array
    {
        $tickets = Ticket::where('registration_id', $registration->id)
            ->with('holderUnit', 'registration.unit', 'registration.event', 'registration.companions.unit', 'registration.tableAssignment.seatingTable')
            ->orderBy('holder_position')
            ->get();
        $ticket = $tickets->firstWhere('holder_position', Ticket::GuestPosition);

        if ($ticket === null) {
            return null;
        }

        // README ecran 15 : le gabarit du billet (modele, elements activables) est un reglage
        // d'organisation, applique ici au billet reellement remis a l'invite, pas seulement a
        // l'apercu du back-office. Meme billet pour l'invite et chacun de ses accompagnateurs
        // (`TicketCard`), sur cette page, sur leur lien individuel et dans le PDF.
        $branding = $tenant->brandingOrCreate();
        $design = TicketCard::design($tenant, $event);

        return [
            'qrImage' => TicketQrCode::dataUri($ticket->signedToken()),
            'card' => TicketCard::for($ticket, $branding),
            'event' => TicketCard::event($event),
            // Un billet par accompagnateur (README 2.8, ecran 7), avec son lien individuel : l'invite
            // le transmet a qui arrivera sans lui.
            'passes' => $tickets
                ->filter(fn (Ticket $pass) => $pass->isCompanion())
                ->map(fn (Ticket $pass) => [
                    'id' => $pass->id,
                    'name' => $pass->holderName(),
                    'unit' => $pass->holderUnitName(),
                    'qrImage' => TicketQrCode::dataUri($pass->signedToken()),
                    'shareUrl' => $pass->shareUrl(),
                    'card' => TicketCard::for($pass, $branding),
                ])
                ->values()
                ->all(),
            'tableNumber' => $registration->tableAssignment?->seatingTable->number,
            // Tous les billets du groupe en un PDF (README 2.8), pour l'entree sans connexion.
            'pdfUrl' => $registration->ticketsPdfUrl(),
            'scheduledSendAt' => $event->invitations_send_at?->toISOString(),
            ...$design,
        ];
    }

    /**
     * Resolve the registration a resume token points to, scoped to this event.
     *
     * La recherche compare l'empreinte, jamais le jeton en clair (CLAUDE.md, « Securite »).
     */
    /**
     * Get the address of this browser's reservation under way on the event, if any.
     */
    private function ongoingReservationUrl(Event $event, string $token): ?string
    {
        $ongoing = OngoingReservation::find($event);

        if ($ongoing === null) {
            return null;
        }

        return route($ongoing['status'] === RegistrationStatus::Draft ? 'public.registrations.verify.show' : 'public.registrations.show', [
            'token' => $token,
            'resume' => $ongoing['resume'],
        ]);
    }

    private function registrationForResumeToken(Event $event, string $resume): Registration
    {
        $registration = Registration::where('event_id', $event->id)
            ->where('resume_token_hash', Registration::hashResumeToken($resume))
            ->first();

        abort_if(! $registration, 404);

        return $registration;
    }

    /**
     * Resolve the published event of a public link, or fail with the same 404 as an unknown
     * token (see `Public\EventController::show`).
     */
    private function publishedEvent(string $token): Event
    {
        $tenant = Tenant::current();
        abort_if(! $tenant, 404);

        $event = Event::where('public_token', $token)->first();
        abort_if(! $event || ! $event->isPublished(), 404);

        return $event;
    }
}
