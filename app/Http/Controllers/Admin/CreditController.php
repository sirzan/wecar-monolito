<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditApplication;
use App\Models\CreditDocument;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CreditDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CreditController extends Controller
{
    public function __construct(private readonly CreditDocumentService $documents) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'status']);

        $applications = CreditApplication::query()
            ->with(['financiera', 'user'])
            ->when($filters['q'] ?? null, function ($query, string $q) {
                $term = '%'.trim($q).'%';
                $query->where(function ($query) use ($term) {
                    $query->whereLike('folio', $term)
                        ->orWhereLike('full_name', $term)
                        ->orWhereLike('email', $term);
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Credits', [
            'applications' => $applications->through(fn (CreditApplication $application) => [
                'id' => $application->getKey(),
                'folio' => $application->folio,
                'full_name' => $application->full_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'car' => trim(($application->car_brand ?? '').' '.($application->car_model ?? '').' '.($application->car_year ?? '')),
                'financiera' => $application->financiera_name,
                'score' => $application->score,
                'risk_level' => $application->risk_level,
                'status' => $application->status,
                'monthly_payment' => (float) $application->monthly_payment,
                'created_at' => $application->created_at?->toIso8601String(),
            ]),
            'filters' => $filters,
            'statuses' => ['en_revision', 'aprobado', 'rechazado'],
            'counts' => [
                'total' => CreditApplication::query()->count(),
                'review' => CreditApplication::query()->where('status', 'en_revision')->count(),
                'approved' => CreditApplication::query()->where('status', 'aprobado')->count(),
                'rejected' => CreditApplication::query()->where('status', 'rechazado')->count(),
            ],
        ]);
    }

    public function show(CreditApplication $application): Response
    {
        $application->load(['documents', 'financiera', 'user', 'car']);

        return Inertia::render('Admin/CreditDetail', [
            'application' => [
                'id' => $application->getKey(),
                'folio' => $application->folio,
                'status' => $application->status,
                'notes' => $application->notes,
                'full_name' => $application->full_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'birthdate' => $application->birthdate?->toDateString(),
                'rfc' => $application->rfc,
                'curp' => $application->curp,
                'address' => [
                    'calle' => $application->calle,
                    'colonia' => $application->colonia,
                    'cp' => $application->cp,
                    'alcaldia' => $application->alcaldia,
                    'ciudad' => $application->ciudad,
                    'pais' => $application->pais,
                ],
                'employment' => [
                    'ocupacion' => $application->ocupacion,
                    'empresa' => $application->empresa,
                    'antiguedad' => $application->antiguedad,
                    'ingreso_mensual' => (float) $application->ingreso_mensual,
                    'deuda_mensual' => (float) $application->deuda_mensual,
                    'banco' => $application->banco,
                    'tipo_cuenta' => $application->tipo_cuenta,
                ],
                'vehicle' => [
                    'brand' => $application->car_brand,
                    'model' => $application->car_model,
                    'version' => $application->car_version,
                    'year' => $application->car_year,
                    'price' => (float) $application->car_price,
                    'km' => $application->car_km,
                ],
                'credit' => [
                    'financiera' => $application->financiera_name,
                    'score' => $application->score,
                    'risk_level' => $application->risk_level,
                    'down_payment' => (float) $application->down_payment,
                    'term_months' => $application->term_months,
                    'monthly_payment' => (float) $application->monthly_payment,
                    'rate' => (float) $application->rate,
                    'cat' => (float) $application->cat,
                    'total_to_pay' => (float) $application->total_to_pay,
                    'service_fee' => (float) $application->service_fee,
                    'financiera_id' => $application->financiera_id,
                    'car_id' => $application->car_id,
                ],
                'documents' => $application->documents->map(fn (CreditDocument $document) => [
                    'slot' => $document->slot,
                    'name' => $document->original_name,
                    'url' => route('admin.credits.document', [$application, $document->slot]),
                ]),
                'created_at' => $application->created_at?->toIso8601String(),
                'submitted_at' => $application->submitted_at?->toIso8601String(),
            ],
            'statuses' => ['en_revision', 'aprobado', 'rechazado'],
            'assignees' => User::query()
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['superadmin', 'manager']))
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->getKey(), 'name' => $user->name]),
        ]);
    }

    public function update(Request $request, CreditApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['en_revision', 'aprobado', 'rechazado'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $application->update($validated);

        if ($application->user_id) {
            $label = match ($application->status) {
                'aprobado' => 'aprobada',
                'rechazado' => 'rechazada',
                default => 'en revisión',
            };

            UserNotification::query()->create([
                'user_id' => $application->user_id,
                'title' => 'Actualización de tu solicitud de crédito',
                'message' => 'Tu solicitud '.$application->folio.' está '.$label.'.',
                'link' => '/panel/tramites',
                'type' => 'credit',
            ]);
        }

        return back()->with('success', 'Solicitud actualizada.');
    }

    public function document(CreditApplication $application, string $slot): BinaryFileResponse
    {
        $document = CreditDocument::query()
            ->where('credit_application_id', $application->getKey())
            ->where('slot', $slot)
            ->latest()
            ->firstOrFail();

        return response()->file($this->documents->downloadPath($document));
    }
}
