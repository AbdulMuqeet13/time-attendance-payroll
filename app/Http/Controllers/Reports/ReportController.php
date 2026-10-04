<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Services\Reports\AttendanceReports;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(Request $request, AttendanceReports $reports): Response
    {
        [$report, $from, $to, $filters] = $this->parameters($request);
        $user = $request->user();

        return Inertia::render('reports/index', [
            'report' => $report,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'result' => $reports->run($report, $user, $from, $to, $filters),
            'branches' => Branch::query()->active()
                ->when($user->branch_id, fn ($query, $branchId) => $query->whereKey($branchId))
                ->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, AttendanceReports $reports): BinaryFileResponse
    {
        [$report, $from, $to, $filters] = $this->parameters($request);
        $result = $reports->run($report, $request->user(), $from, $to, $filters);

        $name = "{$report}-report-{$from->toDateString()}-to-{$to->toDateString()}.xlsx";
        $path = storage_path('app/private/exports/'.Str::random(12).'-'.$name);
        @mkdir(dirname($path), 0755, true);

        $labels = collect($result['columns'])->pluck('label', 'key');
        $writer = SimpleExcelWriter::create($path);

        foreach ($result['rows'] as $row) {
            $writer->addRow($labels->mapWithKeys(fn (string $label, string $key) => [$label => $row[$key] ?? null])->all());
        }

        $writer->close();

        return response()->download($path, $name)->deleteFileAfterSend();
    }

    /**
     * @return array{0: string, 1: CarbonImmutable, 2: CarbonImmutable, 3: array<string, mixed>}
     */
    private function parameters(Request $request): array
    {
        abort_unless($request->user()->can(PermissionEnum::ReportsView->value), 403);

        $data = $request->validate([
            'report' => ['nullable', Rule::in(AttendanceReports::REPORTS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ]);

        $from = CarbonImmutable::parse($data['from'] ?? now()->startOfMonth());
        $to = CarbonImmutable::parse($data['to'] ?? now());

        abort_if($from->diffInDays($to) > 370, 422, 'Reports cover at most one year.');

        return [$data['report'] ?? 'attendance', $from, $to, $data];
    }
}
