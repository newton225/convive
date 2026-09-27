<?php

namespace App\Http\Controllers\Events;

use App\Actions\Reports\GenerateEventReport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Tenant;
use App\Support\EventReport;
use App\Support\PdfLetterhead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Les rapports post-evenement (README ecran 22), etape 9 de « Ordre de construction ».
 *
 * Le rapport n'est jamais stocke : il se recalcule a chaque ouverture (`GenerateEventReport`), un
 * chiffre conserve finirait par diverger des inscriptions et des scans dont il est tire.
 */
class ReportController extends Controller
{
    /**
     * Display the post-event report of the given event.
     */
    public function show(Request $request, Tenant $tenant, Event $event, GenerateEventReport $generate): Response
    {
        Gate::authorize('view', [EventReport::class, $tenant]);

        return Inertia::render('events/report', [
            'tenant' => ['slug' => $tenant->slug],
            'event' => ['id' => $event->id, 'name' => $event->name],
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'report' => $generate->handle($event),
        ]);
    }

    /**
     * Export the post-event report to a PDF document, with the organisation's letterhead, stamp
     * and signature.
     */
    public function exportPdf(Request $request, Tenant $tenant, Event $event, GenerateEventReport $generate): PdfBuilder
    {
        Gate::authorize('export', [EventReport::class, $tenant]);

        // SECURITY.md M3 : un export legitime reste une fuite possible, il laisse une trace.
        activity()
            ->performedOn($event)
            ->event('exported')
            ->withProperties(['format' => 'pdf'])
            ->log('report.exported');

        return Pdf::view('pdf.report', [
            'letterhead' => PdfLetterhead::for($tenant),
            'event' => ['name' => $event->name, 'startsAt' => $event->starts_at],
            'report' => $generate->handle($event),
            'generatedAt' => now(),
            'watermark' => ['name' => $request->user()->name, 'at' => now()],
        ])
            ->format('a4')
            ->download("rapport-{$event->id}.pdf");
    }
}
