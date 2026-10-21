<?php

namespace App\Http\Controllers\Events;

use App\Enums\TreasuryTable;
use App\Exports\TreasuryTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\TreasuryExportRequest;
use App\Http\Requests\Events\TreasuryRequest;
use App\Models\Tenant;
use App\Support\PdfLetterhead;
use App\Support\TreasuryOverview;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * L'ecran Tresorerie : les totaux de toute l'organisation, par evenement et par compte de versement,
 * et les remboursements a faire (decision du 2026-10-16). Rien n'est stocke, tout se recalcule.
 */
class TreasuryController extends Controller
{
    /**
     * Display the money of the whole organisation.
     */
    public function show(TreasuryRequest $request, Tenant $tenant): Response
    {
        return Inertia::render('tenants/treasury', [
            'tenant' => ['slug' => $tenant->slug],
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'filters' => [
                'from' => $request->validated('from'),
                'to' => $request->validated('to'),
            ],
            'refundListLimit' => TreasuryOverview::RefundListLimit,
            ...TreasuryOverview::for($tenant, $request->from(), $request->to()),
        ]);
    }

    /**
     * Export the same figures to a PDF document, with the organisation's letterhead, stamp and
     * signature.
     */
    public function exportPdf(TreasuryExportRequest $request, Tenant $tenant): PdfBuilder
    {
        // SECURITY.md M3 : un export legitime reste une fuite possible, il laisse une trace.
        activity()
            ->causedBy($request->user())
            ->event('exported')
            ->withProperties([
                'format' => 'pdf',
                'from' => $request->validated('from'),
                'to' => $request->validated('to'),
            ])
            ->log('treasury.exported');

        return Pdf::view('pdf.treasury', [
            'letterhead' => PdfLetterhead::for($tenant),
            'overview' => TreasuryOverview::for($tenant, $request->from(), $request->to()),
            'refundListLimit' => TreasuryOverview::RefundListLimit,
            'from' => $request->from(),
            'to' => $request->to(),
            'generatedAt' => now(),
            'watermark' => ['name' => $request->user()->name, 'at' => now()],
        ])
            ->format('a4')
            ->download('tresorerie-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Export one table of the screen to an Excel or CSV file : by event, by payment account, or the
     * refunds to make (the whole list, the screen stops at fifty).
     */
    public function exportTable(TreasuryExportRequest $request, Tenant $tenant, string $table, string $format): BinaryFileResponse
    {
        $table = TreasuryTable::from($table);
        $overview = TreasuryOverview::for($tenant, $request->from(), $request->to(), refundLimit: null);
        $extension = $format === 'csv' ? 'csv' : 'xlsx';

        // SECURITY.md M3 : un export legitime reste une fuite possible, il laisse une trace.
        activity()
            ->causedBy($request->user())
            ->event('exported')
            ->withProperties([
                'format' => $extension,
                'table' => $table->value,
                'rows' => count(match ($table) {
                    TreasuryTable::Events => $overview['events'],
                    TreasuryTable::Accounts => $overview['accounts'],
                    TreasuryTable::Refunds => $overview['refundsDue'],
                }),
                'from' => $request->validated('from'),
                'to' => $request->validated('to'),
            ])
            ->log('treasury.exported');

        return Excel::download(
            new TreasuryTableExport($table, $overview),
            $table->filename().'.'.$extension,
            $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }
}
