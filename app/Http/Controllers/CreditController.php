<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CreditApplication;
use App\Models\CreditDocument;
use App\Models\Financiera;
use App\Models\Profile;
use App\Models\User;
use App\Services\CreditApplicationService;
use App\Services\CreditDocumentService;
use App\Services\CreditEvaluationService;
use App\Services\CreditOfferService;
use App\Services\CreditScoringService;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        private readonly MembershipService $memberships,
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

    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'profile' => $this->buildProfilePayload($request->user()),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:160'],
            'phone' => ['required', 'string', 'min:10', 'max:30'],
            'birthdate' => ['required', 'date', 'before:today'],
            'rfc' => ['nullable', 'string', 'max:20'],
            'curp' => ['nullable', 'string', 'max:30'],
            'calle' => ['required', 'string', 'max:200'],
            'colonia' => ['required', 'string', 'max:120'],
            'cp' => ['required', 'string', 'max:10'],
            'alcaldia' => ['required', 'string', 'max:120'],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:80'],
            'pais' => ['nullable', 'string', 'max:80'],
            'ocupacion' => ['required', 'string', 'max:120'],
            'empresa' => ['nullable', 'string', 'max:160'],
            'antiguedad' => ['nullable', 'string', 'max:80'],
            'antiguedad_anios' => ['nullable', 'numeric', 'min:0', 'max:80'],
            'tipo_trabajo' => ['nullable', 'string', 'max:80'],
            'ingreso_mensual' => ['nullable', 'numeric', 'min:0'],
            'deuda_mensual' => ['nullable', 'numeric', 'min:0'],
            'banco' => ['nullable', 'string', 'max:80'],
            'tipo_cuenta' => ['nullable', 'string', 'max:80'],
        ]);

        $user = $request->user();
        $rfc = filled($validated['rfc'] ?? null) ? Str::upper(trim((string) $validated['rfc'])) : null;
        $curp = filled($validated['curp'] ?? null) ? Str::upper(trim((string) $validated['curp'])) : null;

        $user->update([
            'name' => trim($validated['full_name']),
            'phone' => trim($validated['phone']),
        ]);

        $profileData = [
            'full_name' => trim($validated['full_name']),
            'phone' => trim($validated['phone']),
            'birthdate' => $validated['birthdate'],
            'rfc' => $rfc,
            'curp' => $curp,
            'calle' => trim($validated['calle']),
            'colonia' => trim($validated['colonia']),
            'cp' => trim($validated['cp']),
            'alcaldia' => trim($validated['alcaldia']),
            'city' => filled($validated['ciudad'] ?? null) ? trim((string) $validated['ciudad']) : trim($validated['alcaldia']),
            'state' => filled($validated['state'] ?? null) ? trim((string) $validated['state']) : $user->profile?->state,
            'pais' => filled($validated['pais'] ?? null) ? trim((string) $validated['pais']) : 'México',
            'ocupacion' => trim($validated['ocupacion']),
            'empresa' => filled($validated['empresa'] ?? null) ? trim((string) $validated['empresa']) : null,
            'antiguedad' => filled($validated['antiguedad'] ?? null) ? trim((string) $validated['antiguedad']) : $user->profile?->antiguedad,
        ];

        if (isset($validated['antiguedad_anios'])) {
            $profileData['antiguedad_anios'] = (int) round((float) $validated['antiguedad_anios']);
        }

        if (filled($validated['tipo_trabajo'] ?? null)) {
            $profileData['tipo_trabajo'] = trim((string) $validated['tipo_trabajo']);
        }

        if (isset($validated['ingreso_mensual']) && (float) $validated['ingreso_mensual'] > 0) {
            $profileData['ingreso_mensual'] = (float) $validated['ingreso_mensual'];
        }

        if (isset($validated['deuda_mensual'])) {
            $profileData['deuda_mensual'] = (float) $validated['deuda_mensual'];
        }

        if (filled($validated['banco'] ?? null)) {
            $profileData['banco'] = trim((string) $validated['banco']);
        }

        if (filled($validated['tipo_cuenta'] ?? null)) {
            $profileData['tipo_cuenta'] = trim((string) $validated['tipo_cuenta']);
        }

        Profile::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            $profileData,
        );

        $user->unsetRelation('profile');

        return response()->json([
            'success' => true,
            'profile' => $this->buildProfilePayload($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProfilePayload(User $user): array
    {
        $profile = $user->profile;
        $lastApplication = CreditApplication::query()
            ->where('user_id', $user->getKey())
            ->latest('submitted_at')
            ->latest('created_at')
            ->first();

        $pick = static function (mixed ...$values): mixed {
            foreach ($values as $value) {
                if (is_string($value)) {
                    if (trim($value) !== '') {
                        return trim($value);
                    }

                    continue;
                }

                if ($value !== null) {
                    return $value;
                }
            }

            return null;
        };

        $fullName = $pick($profile?->full_name, $user->name, $lastApplication?->full_name);
        $email = $pick($user->email, $lastApplication?->email);
        $phone = $pick($profile?->phone, $user->phone, $lastApplication?->phone);
        $birthdate = $pick(
            $profile?->birthdate?->toDateString(),
            $lastApplication?->birthdate?->toDateString(),
        );
        $calle = $pick($profile?->calle, $lastApplication?->calle);
        $colonia = $pick($profile?->colonia, $lastApplication?->colonia);
        $cp = $pick($profile?->cp, $lastApplication?->cp);
        $alcaldia = $pick($profile?->alcaldia, $lastApplication?->alcaldia, $profile?->city, $lastApplication?->ciudad);
        $ciudad = $pick($profile?->city, $lastApplication?->ciudad, $alcaldia);
        $ocupacion = $pick($profile?->ocupacion, data_get($profile?->metadata, 'ocupacion'), $lastApplication?->ocupacion);

        $accountComplete = filled($fullName)
            && filled($email)
            && filled($phone)
            && filled($birthdate)
            && filled($calle)
            && filled($colonia)
            && filled($cp)
            && filled($alcaldia)
            && filled($ocupacion);

        return [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'birthdate' => $birthdate,
            'rfc' => $pick($profile?->rfc, $lastApplication?->rfc),
            'curp' => $pick($profile?->curp, $lastApplication?->curp),
            'gender' => $profile?->genero,
            'calle' => $calle,
            'colonia' => $colonia,
            'cp' => $cp,
            'alcaldia' => $alcaldia,
            'ciudad' => $ciudad,
            'state' => $pick($profile?->state),
            'pais' => $pick($profile?->pais, $lastApplication?->pais) ?? 'México',
            'ocupacion' => $ocupacion,
            'empresa' => $pick($profile?->empresa, data_get($profile?->metadata, 'empresa'), $lastApplication?->empresa),
            'antiguedad' => $pick($profile?->antiguedad, data_get($profile?->metadata, 'antiguedad'), $lastApplication?->antiguedad),
            'antiguedad_anios' => $profile?->antiguedad_anios,
            'tipo_trabajo' => $pick($profile?->tipo_trabajo, data_get($profile?->metadata, 'tipo_trabajo')),
            'ingreso_mensual' => $pick($profile?->ingreso_mensual, data_get($profile?->metadata, 'ingreso_mensual'), $lastApplication?->ingreso_mensual),
            'deuda_mensual' => $pick($profile?->deuda_mensual, data_get($profile?->metadata, 'deuda_mensual'), $lastApplication?->deuda_mensual),
            'banco' => $pick($profile?->banco, data_get($profile?->metadata, 'banco'), $lastApplication?->banco),
            'tipo_cuenta' => $pick($profile?->tipo_cuenta, data_get($profile?->metadata, 'tipo_cuenta'), $lastApplication?->tipo_cuenta),
            'account_complete' => $accountComplete,
        ];
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
            'perfil.antiguedadLaboralAnios' => ['required', 'numeric', 'min:0'],
            'perfil.deudasMensuales' => ['nullable', 'numeric', 'min:0'],
            'perfil.edad' => ['required', 'integer', 'min:18', 'max:99'],
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
            'ciudad' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:80'],
            'pais' => ['required', 'string', 'max:80'],
            'ocupacion' => ['required', 'string', 'max:120'],
            'empresa' => ['nullable', 'string', 'max:160'],
            'antiguedad' => ['nullable', 'string', 'max:80'],
            'ingreso_mensual' => ['required', 'numeric', 'min:1'],
            'deuda_mensual' => ['nullable', 'numeric', 'min:0'],
            'banco' => ['nullable', 'string', 'max:80'],
            'tipo_cuenta' => ['nullable', 'string', 'max:80'],
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
            'bureau' => ['required', Rule::in(['excelente', 'bueno', 'regular', 'sin-historial', 'malo'])],
            'employment' => ['required', Rule::in(['fijo', 'honorarios', 'negocio', 'eventual'])],
            'tenure' => ['required', 'numeric', 'min:0'],
            'age' => ['required', 'integer', 'min:18', 'max:99'],
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

        $validated['rfc'] = filled($validated['rfc'] ?? null) ? Str::upper((string) $validated['rfc']) : null;
        $validated['curp'] = filled($validated['curp'] ?? null) ? Str::upper((string) $validated['curp']) : null;

        if ($validated['car_id'] ?? null) {
            $car = Car::query()
                ->with(['brand', 'model', 'version'])
                ->whereKey($validated['car_id'])
                ->where('owner_id', $request->user()->getKey())
                ->first();

            if (! $car) {
                throw ValidationException::withMessages([
                    'car_id' => 'El auto seleccionado no pertenece a tu cuenta.',
                ]);
            }

            $validated['car_brand'] = $car->brand?->name ?? $validated['car_brand'];
            $validated['car_model'] = $car->model?->name ?? $validated['car_model'];
            $validated['car_version'] = $car->version?->name ?? $validated['car_version'];
            $validated['car_year'] = (int) $car->year;
            $validated['car_price'] = (float) $car->price;
            $validated['car_km'] = $car->km;
        }

        if ((float) $validated['down_payment'] > (float) $validated['car_price']) {
            throw ValidationException::withMessages([
                'down_payment' => 'El enganche no puede ser mayor que el precio del auto.',
            ]);
        }

        $input = [
            'ingreso' => (float) $validated['ingreso_mensual'],
            'edad' => (int) $validated['age'],
            'antiguedad' => (float) $validated['tenure'],
            'empleo' => (string) $validated['employment'],
            'historial' => (string) $validated['bureau'],
            'deudas' => (float) ($validated['deuda_mensual'] ?? 0),
            'precio' => (float) $validated['car_price'],
            'enganche' => (float) $validated['down_payment'],
            'plazo' => (int) $validated['term_months'],
            'car_year' => (int) $validated['car_year'],
            'brand' => (string) $validated['car_brand'],
            'vehicle_condition' => (string) ($validated['car_condition'] ?? 'used'),
        ];

        $score = $this->scoring->calculate($input);
        $offer = collect($this->offers->generate(
            $input,
            $score['score'],
            collect([$financiera]),
            $this->memberships->isSubscribed($request->user()),
        ))->first();

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
            'score', 'risk_level', 'monthly_payment', 'rate', 'cat', 'total_to_pay',
            'inputs', 'offers', 'financiera_name', 'state',
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
            'offers' => [$offer],
        ];

        $application = $this->applications->submit(
            $request->user(),
            $data,
            $files->all(),
            $reuse,
            [
                'state' => $validated['state'] ?? null,
                'antiguedad_anios' => isset($validated['tenure']) ? (int) round((float) $validated['tenure']) : null,
                'tipo_trabajo' => $validated['employment'] ?? null,
            ],
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
