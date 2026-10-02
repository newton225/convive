<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\SendPaymentReminder;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Console\RecoveryOverview;
use App\Support\Console\RevenueOverview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le recouvrement (README ecran 29) : impayes, relances, suspensions a venir et paiements en
 * echec, lus dans la base centrale. La zone `recovery` des routes le reserve aux Fondateurs et a
 * la Comptabilite.
 */
class RecoveryController extends Controller
{
    /**
     * Display the overdue subscriptions and the failed payments.
     */
    public function index(): Response
    {
        $unpaid = RecoveryOverview::unpaid();

        return Inertia::render('console/recovery', [
            'isSample' => false,
            'unpaid' => $unpaid,
            'amountsDue' => RecoveryOverview::amountsDue($unpaid),
            'failedPayments' => RecoveryOverview::failedPayments(),
            'revenue' => RevenueOverview::summary(),
        ]);
    }

    /**
     * Send a payment reminder to the given organisation.
     */
    public function remind(Request $request, Tenant $tenant, SendPaymentReminder $send): RedirectResponse
    {
        $send->handle($tenant, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('console.recovery.flash.reminded', ['organisation' => $tenant->name])]);

        return to_route('console.recovery');
    }
}
