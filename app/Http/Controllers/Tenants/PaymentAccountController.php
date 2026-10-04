<?php

namespace App\Http\Controllers\Tenants;

use App\Actions\Tenants\SavePaymentAccount;
use App\Enums\PaymentChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\SavePaymentAccountRequest;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentAccountController extends Controller
{
    /**
     * Display the payment accounts of the tenant.
     */
    public function index(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('viewAny', [PaymentAccount::class, $tenant]);

        $user = $request->user();

        return Inertia::render('tenants/payment-accounts', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'accounts' => PaymentAccount::ordered()->with('requester')->get()
                ->map(fn (PaymentAccount $account) => [
                    'id' => $account->id,
                    'label' => $account->label,
                    'channel' => $account->channel?->value,
                    'channelLabel' => $account->channel?->label(),
                    'accountNumber' => $account->account_number,
                    'holderName' => $account->holder_name,
                    'instructions' => $account->instructions,
                    'isActive' => $account->is_active,
                    'isPubliclyVisible' => $account->isPubliclyVisible(),
                    'changedRecently' => $account->changedRecently(),
                    'pending' => $account->hasPendingChange() ? [
                        'channelLabel' => $account->pending_channel?->label(),
                        'accountNumber' => $account->pending_account_number,
                        'holderName' => $account->pending_holder_name,
                        'activatesAt' => $account->pending_activates_at?->toISOString(),
                        'requestedBy' => $account->requester?->name,
                        'mayApprove' => Gate::allows('approve', [$account, $tenant]),
                    ] : null,
                ]),
            'channels' => array_map(fn (PaymentChannel $channel) => [
                'value' => $channel->value,
                'label' => $channel->label(),
                'hasAccountNumber' => $channel->hasAccountNumber(),
            ], PaymentChannel::cases()),
            'activationDelayHours' => PaymentAccount::ActivationDelayHours,
            'isOwner' => $user->ownsTenant($tenant),
            // Toute modification exige le code a deux facteurs : sans double authentification, la
            // page le dit avant la saisie plutot qu'apres.
            'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
        ]);
    }

    /**
     * Request a new payment account.
     */
    public function store(SavePaymentAccountRequest $request, Tenant $tenant, SavePaymentAccount $save): RedirectResponse
    {
        $save->request($tenant, null, $this->attributes($request), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('payment_accounts.flash.change_requested')]);

        return to_route('tenants.payment-accounts.index', $tenant);
    }

    /**
     * Request a change on an existing payment account.
     */
    public function update(SavePaymentAccountRequest $request, Tenant $tenant, PaymentAccount $paymentAccount, SavePaymentAccount $save): RedirectResponse
    {
        $account = $save->request($tenant, $paymentAccount, $this->attributes($request), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $account->hasPendingChange()
                ? __('payment_accounts.flash.change_requested')
                : __('payment_accounts.flash.updated'),
        ]);

        return to_route('tenants.payment-accounts.index', $tenant);
    }

    /**
     * Let a second owner lift the activation delay.
     */
    public function approve(Request $request, Tenant $tenant, PaymentAccount $paymentAccount, SavePaymentAccount $save): RedirectResponse
    {
        Gate::authorize('approve', [$paymentAccount, $tenant]);

        $save->approve($paymentAccount, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('payment_accounts.flash.change_approved')]);

        return to_route('tenants.payment-accounts.index', $tenant);
    }

    /**
     * Cancel a pending change.
     */
    public function cancel(Request $request, Tenant $tenant, PaymentAccount $paymentAccount, SavePaymentAccount $save): RedirectResponse
    {
        Gate::authorize('cancel', [$paymentAccount, $tenant]);

        $save->cancel($paymentAccount, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('payment_accounts.flash.change_cancelled')]);

        return to_route('tenants.payment-accounts.index', $tenant);
    }

    /**
     * Delete the specified payment account.
     */
    public function destroy(Tenant $tenant, PaymentAccount $paymentAccount): RedirectResponse
    {
        Gate::authorize('delete', [$paymentAccount, $tenant]);

        // Des preuves designent ce compte : le supprimer effacerait ou l'argent a ete verse. Il se
        // desactive, ce qui le retire des liens publics sans toucher a l'historique.
        if (PaymentProof::where('payment_account_id', $paymentAccount->id)->exists()) {
            return back()->withErrors(['payment_account' => __('payment_accounts.errors.has_proofs')]);
        }

        activity()
            ->event('deleted')
            ->withProperties([
                'old' => [
                    'label' => $paymentAccount->label,
                    'channel' => $paymentAccount->channel?->value,
                    'account_number' => $paymentAccount->account_number,
                ],
            ])
            ->log('payment_account.deleted');

        $paymentAccount->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('payment_accounts.flash.deleted')]);

        return to_route('tenants.payment-accounts.index', $tenant);
    }

    /**
     * @return array{label: string, channel: string, account_number: ?string, holder_name: ?string, instructions: ?string, is_active: bool}
     */
    private function attributes(SavePaymentAccountRequest $request): array
    {
        return [
            'label' => $request->validated('label'),
            'channel' => $request->validated('channel'),
            'account_number' => $request->validated('account_number'),
            'holder_name' => $request->validated('holder_name'),
            'instructions' => $request->validated('instructions'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
