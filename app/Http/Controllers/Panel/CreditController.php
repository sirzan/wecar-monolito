<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CreditApplication;
use App\Models\CreditDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request): Response
    {
        $applications = CreditApplication::query()
            ->where('user_id', $request->user()->getKey())
            ->with(['documents', 'financiera'])
            ->latest()
            ->paginate(10);

        return Inertia::render('Panel/Credits', [
            'applications' => $applications->through(fn (CreditApplication $application) => [
                'id' => $application->getKey(),
                'folio' => $application->folio,
                'status' => $application->status,
                'car' => trim(($application->car_brand ?? '').' '.($application->car_model ?? '').' '.($application->car_year ?? '')),
                'financiera' => $application->financiera_name,
                'score' => $application->score,
                'risk_level' => $application->risk_level,
                'down_payment' => (float) $application->down_payment,
                'term_months' => $application->term_months,
                'monthly_payment' => (float) $application->monthly_payment,
                'created_at' => $application->created_at?->toIso8601String(),
                'documents' => $application->documents->map(fn (CreditDocument $document) => [
                    'slot' => $document->slot,
                    'name' => $document->original_name,
                    'url' => route('credit.documents.show', [$application, $document->slot]),
                ]),
            ]),
            'counts' => [
                'total' => CreditApplication::query()->where('user_id', $request->user()->getKey())->count(),
                'review' => CreditApplication::query()->where('user_id', $request->user()->getKey())->where('status', 'en_revision')->count(),
                'approved' => CreditApplication::query()->where('user_id', $request->user()->getKey())->where('status', 'aprobado')->count(),
            ],
        ]);
    }
}
