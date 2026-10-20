<?php

namespace App\Services;

use App\Mail\CreditApplicationReceived;
use App\Mail\CreditApplicationSubmitted;
use App\Models\CreditApplication;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreditApplicationService
{
    public function __construct(private readonly CreditDocumentService $documents) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile>  $files
     * @param  array<int, string>  $reuse
     */
    public function submit(User $user, array $data, array $files, array $reuse = []): CreditApplication
    {
        $recent = CreditApplication::query()
            ->where('email', Str::lower($data['email']))
            ->where('car_brand', $data['car_brand'] ?? null)
            ->where('car_model', $data['car_model'] ?? null)
            ->where('financiera_name', $data['financiera_name'] ?? null)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($recent) {
            throw ValidationException::withMessages([
                'application' => 'Ya enviaste esta misma solicitud en las últimas 24 horas. Te contactaremos pronto.',
            ]);
        }

        return DB::transaction(function () use ($user, $data, $files, $reuse) {
            $application = CreditApplication::query()->create([
                ...$data,
                'user_id' => $user->getKey(),
                'folio' => 'WC-'.Str::upper(Str::random(8)),
                'email' => Str::lower($data['email']),
                'status' => 'en_revision',
                'submitted_at' => now(),
            ]);

            foreach (['ine', 'domicilio', 'estado_cuenta'] as $slot) {
                if (isset($files[$slot])) {
                    $this->documents->store($user, $slot, $files[$slot], $application);
                } elseif (in_array($slot, $reuse, true)) {
                    $this->documents->reuse($user, $slot, $application);
                } else {
                    throw ValidationException::withMessages([
                        $slot => 'Este documento es obligatorio.',
                    ]);
                }
            }

            $this->updateProfile($user, $data);

            return $application->fresh(['documents', 'financiera', 'car']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateProfile(User $user, array $data): void
    {
        Profile::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'birthdate' => $data['birthdate'] ?? null,
                'rfc' => $data['rfc'] ?? null,
                'curp' => $data['curp'] ?? null,
                'calle' => $data['calle'] ?? null,
                'colonia' => $data['colonia'] ?? null,
                'cp' => $data['cp'] ?? null,
                'alcaldia' => $data['alcaldia'] ?? null,
                'city' => $data['ciudad'] ?? null,
                'pais' => $data['pais'] ?? 'México',
                'ocupacion' => $data['ocupacion'] ?? null,
                'empresa' => $data['empresa'] ?? null,
                'antiguedad' => $data['antiguedad'] ?? null,
                'ingreso_mensual' => $data['ingreso_mensual'] ?? null,
                'banco' => $data['banco'] ?? null,
                'tipo_cuenta' => $data['tipo_cuenta'] ?? null,
                'enganche_disponible' => $data['down_payment'] ?? null,
            ],
        );
    }

    public function notify(CreditApplication $application): void
    {
        $application->loadMissing(['user', 'documents']);

        $this->notifyAdmins($application);

        if ($application->user) {
            Mail::to($application->user->email)->queue(new CreditApplicationReceived($application));
        }

        Mail::to('creditos@wecar.mx')->queue(new CreditApplicationSubmitted($application));
    }

    private function notifyAdmins(CreditApplication $application): void
    {
        $admins = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['superadmin', 'manager']))
            ->get();

        foreach ($admins as $admin) {
            UserNotification::query()->create([
                'user_id' => $admin->getKey(),
                'title' => 'Nueva solicitud de crédito',
                'message' => $application->full_name.' envió una solicitud para '.$application->car_brand.' '.$application->car_model.'.',
                'link' => '/admin/creditos/'.$application->getKey(),
                'type' => 'credit',
            ]);
        }
    }
}
