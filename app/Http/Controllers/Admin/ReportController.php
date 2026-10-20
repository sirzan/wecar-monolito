<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $reports = CarReport::query()
            ->with(['car.brand', 'car.model', 'reporter'])
            ->when($status, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Reports', [
            'reports' => $reports->through(fn (CarReport $report) => [
                'id' => $report->getKey(),
                'reason' => $report->reason,
                'details' => $report->details,
                'status' => $report->status,
                'car' => trim(($report->car?->brand?->name ?? '').' '.($report->car?->model?->name ?? '')),
                'car_slug' => $report->car?->slug,
                'reporter' => $report->reporter?->name ?? 'Anónimo',
                'created_at' => $report->created_at?->toIso8601String(),
            ]),
            'filters' => ['status' => $status],
            'statuses' => ['open', 'resolved', 'dismissed'],
        ]);
    }

    public function update(Request $request, CarReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'resolved', 'dismissed'])],
        ]);

        $report->update([
            'status' => $validated['status'],
            'resolved_at' => $validated['status'] === 'open' ? null : now(),
        ]);

        return back()->with('success', 'Reporte actualizado.');
    }
}
