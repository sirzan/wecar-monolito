<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Car;
use App\Models\CarLead;
use App\Models\CarReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $since = now()->subDays(30);

        $series = function (string $table) use ($since) {
            return DB::table($table)
                ->where('created_at', '>=', $since)
                ->pluck('created_at')
                ->groupBy(fn ($value) => substr((string) $value, 0, 10))
                ->map(fn ($values, $date) => ['date' => $date, 'count' => $values->count()])
                ->sortKeys()
                ->values();
        };

        $recentReports = CarReport::query()
            ->with(['car.brand', 'car.model'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (CarReport $report) => [
                'id' => $report->getKey(),
                'reason' => $report->reason,
                'status' => $report->status,
                'car' => trim(($report->car?->brand?->name ?? '').' '.($report->car?->model?->name ?? '')),
                'created_at' => $report->created_at?->toIso8601String(),
            ]);

        $recentLeads = CarLead::query()
            ->with(['car.brand', 'car.model'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (CarLead $lead) => [
                'id' => $lead->getKey(),
                'contact_name' => $lead->contact_name,
                'status' => $lead->status,
                'car' => trim(($lead->car?->brand?->name ?? '').' '.($lead->car?->model?->name ?? '')),
                'created_at' => $lead->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'counts' => [
                'users' => User::query()->count(),
                'new_users' => User::query()->where('created_at', '>=', $since)->count(),
                'cars' => Car::query()->count(),
                'active_cars' => Car::query()->whereHas('status', fn ($query) => $query->where('name', 'en_venta'))->count(),
                'agencies' => Agency::query()->count(),
                'active_agencies' => Agency::query()->where('is_active', true)->count(),
                'leads' => CarLead::query()->count(),
                'reports_open' => CarReport::query()->where('status', 'open')->count(),
            ],
            'series' => [
                'users' => $series('users'),
                'cars' => $series('cars'),
                'leads' => $series('car_leads'),
            ],
            'recentReports' => $recentReports,
            'recentLeads' => $recentLeads,
        ]);
    }
}
