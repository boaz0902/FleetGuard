<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFiltersRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ReportsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(ReportFiltersRequest $request, ReportsService $reports): View
    {
        $filters = $request->reportFilters();
        $now = Carbon::now(config('app.timezone'));

        return view('admin.reports.index', [
            ...$reports->report($filters, $now),
            'filters' => $filters,
            'now' => $now,
        ]);
    }

    public function export(ReportFiltersRequest $request, ReportsService $reports): StreamedResponse
    {
        $filters = $request->reportFilters();
        $now = Carbon::now(config('app.timezone'));

        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => 'report_exported',
            'subject_type' => User::class,
            'subject_id' => Auth::id(),
            'changes' => [
                'report_type' => 'admin_reports',
                'filters' => $filters,
            ],
            'created_at' => $now,
        ]);

        return response()->streamDownload(function () use ($filters, $now, $reports): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new RuntimeException('Unable to open the report CSV output stream.');
            }

            if (fwrite($stream, "\xEF\xBB\xBF") === false) {
                throw new RuntimeException('Unable to write the report CSV output stream.');
            }

            $headers = ['Section', 'Metric', 'Vehicle / Customer', 'Category', 'Bookings', 'Days', 'Value', 'Notes'];
            if (fputcsv($stream, array_map(ReportsService::safeCsvCell(...), $headers), ',', '"', '') === false) {
                throw new RuntimeException('Unable to write the report CSV header.');
            }

            foreach ($reports->exportRows($filters, $now) as $row) {
                if (fputcsv($stream, array_map(ReportsService::safeCsvCell(...), $row), ',', '"', '') === false) {
                    throw new RuntimeException('Unable to write a report CSV row.');
                }
            }

            if (! fclose($stream)) {
                throw new RuntimeException('Unable to close the report CSV output stream.');
            }
        }, "fleetguard-report-{$filters['start_date']}-{$filters['end_date']}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
