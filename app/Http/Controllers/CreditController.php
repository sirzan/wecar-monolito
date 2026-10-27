<?php

namespace App\Http\Controllers;

use App\Models\CreditApplication;
use App\Models\CreditDocument;
use App\Models\Financiera;
use App\Services\CreditApplicationService;
use App\Services\CreditDocumentService;
use App\Services\CreditEvaluationService;
use App\Services\CreditOfferService;
use App\Services\CreditScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CreditController extends Controller
{
    public function __construct(
        private readonly CreditEvaluationService $evaluation,
        private readonly CreditApplicationService $applications,
        private readonly CreditDocumentService $documents,
        private readonly CreditScoringService $scoring,
        private readonly CreditOfferService $offers,
    ) {}

    public function financieras(): JsonResponse
    {
        return response()->json([
            'financieras' => Financiera::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->get(),
        ]);
    }

    public function evaluate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auto' => ['required', 'array'],
            'auto.marca' => ['required', 'string', 'max:100'],
            'auto.modelo' => ['required', 'string', 'max:100'],
            'auto.anio' => ['required', 'integer', 'min:1990', 'max:'.(date('Y') + 2)],
            'auto.precio' => ['required', 'numeric', 'min:1'],
            'auto.km' => ['nullable', 'integer', 'min:0'],
            'auto.tipo' => ['nullable', Rule::in(['nuevo', 'seminuevo'])],
            'perfil' => ['required', 'array'],
            'perfil.ingresoMensual' => ['required', 'numeric', 'min:1'],
            'perfil.enganche' => ['required', 'numeric', 'min:0'],
            'perfil.plazoMeses' => ['required', 'integer', 'min:12', 'max:84'],
            'perfil.historialBuro' => ['required', Rule::in(['excelente', 'bueno', 'regular', 'sin-historial', 'malo'])],
            'perfil.tipoEmpleo' => ['required', Rule::in(['fijo', 'honorarios', 'negocio', 'eventual'])],
            'perfil.antiguedadLaboralAnios' => ['nullable', 'numeric', 'min:0'],
            'perfil.deudasMensuales' => ['nullable', 'numeric', 'min:0'],
            'perfil.edad' => ['nullable', 'integer', 'min:18', 'max:99'],
        ]);

        return response()->json(
            $this->evaluation->evaluate($validated['auto'], $validated['perfil']),
        );
    }

    public function documents(Request $request): JsonResponse
    {
        return response()->json([
            'documents' => $this->documents->userDocuments($request->user()),
        ]);
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slot' => ['required', Rule::in(['ine', 'domicilio', 'estado_cuenta'])],
            'file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $document = $this->documents->store(
            $request->user(),
            $validated['slot'],
            $request->file('file'),
        );

        return response()->json([
            'success' => true,
            'document' => [
                'slot' => $document->slot,
                'name' => $document->original_name,
            ],
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:30'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'rfc' => ['nullable', 'string', 'max:20'],
            'curp' => ['nullable', 'string', 'max:30'],
            'calle' => ['required', 'string', 'max:200'],
            'colonia' => ['required', 'string', 'max:120'],
            'cp' => ['required', 'string', 'max:10'],
            'alcaldia' => ['required', 'string', 'max:120'],
            'ciudad' => ['required', 'string', 'max:120'],
            'pais' => ['required', 'string', 'max:80'],
            'ocupacion' => ['required', 'string', 'max:120'],
            'empresa' => ['nullable', 'string', 'max:160'],
            'antiguedad' => ['nullable', 'string', 'max:80'],
            'ingreso_mensual' => ['required', 'numeric', 'min:1'],
            'deuda_mensual' => ['nullable', 'numeric', 'min:0'],
            'banco' => ['required', 'string', 'max:80'],
            'tipo_cuenta' => ['required', 'string', 'max:80'],
            'car_brand' => ['required', 'string', 'max:120'],
            'car_model' => ['required', 'string', 'max:120'],
            'car_version' => ['nullable', 'string', 'max:160'],
            'car_year' => ['required', 'integer', 'min:1990', 'max:'.(date('Y') + 2)],
            'car_price' => ['required', 'numeric', 'min:1'],
            'car_km' => ['nullable', 'integer', 'min:0'],
            'financiera_id' => ['required', 'uuid', 'exists:financieras,id'],
            'financiera_name' => ['required', 'string', 'max:160'],
            'car_id' => ['nullable', 'uuid', 'exists:cars,id'],
            'car_condition' => ['nullable', Rule::in(['new', 'used'])],
            'bureau' => ['nullable', Rule::in(['excelente', 'bueno', 'regular', 'sin-historial', 'malo'])],
            'employment' => ['nullable', Rule::in(['fijo', 'honorarios', 'negocio', 'eventual'])],
            'tenure' => ['nullable', 'numeric', 'min:0'],
            'age' => ['nullable', 'integer', 'min:18', 'max:99'],
            'score' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'risk_level' => ['nullable', 'string', 'max:40'],
            'down_payment' => ['required', 'numeric', 'min:0'],
            'term_months' => ['required', 'integer', 'min:12', 'max:84'],
            'monthly_payment' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'cat' => ['nullable', 'numeric', 'min:0'],
            'total_to_pay' => ['nullable', 'numeric', 'min:0'],
            'inputs' => ['nullable', 'array'],
            'offers' => ['nullable', 'array'],
            'ine' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'domicilio' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'estado_cuenta' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'reuse_ine' => ['nullable', 'boolean'],
            'reuse_domicilio' => ['nullable', 'boolean'],
            'reuse_estado_cuenta' => ['nullable', 'boolean'],
        ]);

        $financiera = Financiera::query()
            ->whereKey($validated['financiera_id'])
            ->where('activo', true)
            ->firstOrFail();

        $input = [
            'ingreso' => (float) $validated['ingreso_mensual'],
            'edad' => (int) ($validated['age'] ?? 30),
            'antiguedad' => (float) ($validated['tenure'] ?? 0),
            'empleo' => (string) ($validated['employment'] ?? 'fijo'),
            'historial' => (string) ($validated['bureau'] ?? 'bueno'),
            'deudas' => (float) ($validated['deuda_mensual'] ?? 0),
            'precio' => (float) $validated['car_price'],
            'enganche' => (float) $validated['down_payment'],
            'plazo' => (int) $validated['term_months'],
            'car_year' => (int) $validated['car_year'],
            'brand' => (string) $validated['car_brand'],
            'vehicle_condition' => (string) ($validated['car_condition'] ?? 'used'),
        ];

        $score = $this->scoring->calculate($input);
        $offer = collect($this->offers->generate($input, $score['score'], collect([$financiera])))->first();

        if (! $offer || ! $offer['elegible']) {
            throw ValidationException::withMessages([
                'financiera_id' => $offer['motivo'] ?? 'La financiera seleccionada no es elegible para este perfil.',
            ]);
        }

        $files = collect(['ine', 'domicilio', 'estado_cuenta'])
            ->mapWithKeys(fn (string $slot) => [$slot => $request->file($slot)])
            ->filter();

        $reuse = collect(['ine', 'domicilio', 'estado_cuenta'])
            ->filter(fn (string $slot) => $request->boolean('reuse_'.$slot))
            ->values()
            ->all();

        $data = collect($validated)->except([
            'ine', 'domicilio', 'estado_cuenta',
            'reuse_ine', 'reuse_domicilio', 'reuse_estado_cuenta',
            'car_condition', 'bureau', 'employment', 'tenure', 'age',
        ])->all();

        $data = [
            ...$data,
            'financiera_id' => $financiera->getKey(),
            'financiera_name' => $financiera->nombre,
            'score' => $score['score'],
            'risk_level' => $this->scoring->riskLevel($score['score'], $score['dti']),
            'monthly_payment' => $offer['mensualidad'],
            'rate' => $offer['tasa'],
            'cat' => $offer['cat'],
            'total_to_pay' => $offer['total'],
            'term_months' => $offer['plazo_efectivo'],
            'service_fee' => $offer['cargo_servicio'],
            'inputs' => [
                ...($validated['inputs'] ?? []),
                'quote' => [
                    'financiera_id' => $financiera->getKey(),
                    'tasa' => $offer['tasa'],
                    'cat' => $offer['cat'],
                    'mensualidad' => $offer['mensualidad'],
                    'plazo_efectivo' => $offer['plazo_efectivo'],
                    'cargo_servicio' => $offer['cargo_servicio'],
                    'total' => $offer['total'],
                ],
            ],
        ];

        $application = $this->applications->submit(
            $request->user(),
            $data,
            $files->all(),
            $reuse,
        );

        $this->applications->notify($application);

        return response()->json([
            'success' => true,
            'id' => $application->getKey(),
            'folio' => $application->folio,
        ]);
    }

    public function showDocument(Request $request, CreditApplication $application, string $slot): BinaryFileResponse
    {
        abort_unless(
            $request->user()->getKey() === $application->user_id || $request->user()->hasAnyRole(['superadmin', 'manager', 'viewer']),
            403,
        );

        $document = CreditDocument::query()
            ->where('credit_application_id', $application->getKey())
            ->where('slot', $slot)
            ->latest()
            ->firstOrFail();

        return response()->file($this->documents->downloadPath($document));
    }
}
