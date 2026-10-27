<?php

namespace App\Http\Controllers;

use App\Exceptions\DiditException;
use App\Models\Profile;
use App\Services\DiditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiditController extends Controller
{
    public function __construct(private readonly DiditService $didit) {}

    public function createSession(Request $request): JsonResponse
    {
        if (! $this->didit->configured()) {
            return response()->json(['error' => 'Didit no está configurado.'], 422);
        }

        $status = $request->user()->profile?->didit_status;

        if ($status === 'approved') {
            return response()->json(['error' => 'Tu identidad ya está verificada.'], 409);
        }

        if ($status === 'in_review') {
            return response()->json(['error' => 'Tu verificación está en revisión.'], 409);
        }

        try {
            $session = $this->didit->createSession($request->user());

            return response()->json($session);
        } catch (DiditException $exception) {
            Log::error('Didit session failed', [
                'user_id' => $request->user()->getKey(),
                'status' => $exception->statusCode,
                'message' => $exception->diditMessage,
                'context' => $exception->context,
            ]);

            $message = match (true) {
                in_array($exception->statusCode, [401, 403], true) => 'La verificación no está disponible en este momento. Contacta a soporte.',
                $exception->statusCode === 400 => 'No se pudo iniciar la verificación. Revisa la configuración del flujo de identidad.',
                $exception->statusCode === 429 => 'Demasiados intentos de verificación. Intenta de nuevo en unos minutos.',
                default => 'No se pudo iniciar la verificación de identidad.',
            };

            return response()->json(['error' => $message], $exception->statusCode === 429 ? 429 : 502);
        } catch (\Throwable $exception) {
            Log::error('Didit session request crashed', [
                'user_id' => $request->user()->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'No se pudo iniciar la verificación de identidad.'], 502);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $sessionId = (string) ($request->query('verificationSessionId') ?? $request->query('session_id') ?? '');
        $profile = $request->user()->profile;

        if (! $profile || $sessionId === '' || ! hash_equals((string) $profile->didit_session_id, $sessionId)) {
            return redirect()
                ->route('panel.dashboard')
                ->with('error', 'No pudimos identificar tu sesión de verificación.');
        }

        $decision = $this->didit->fetchDecision($sessionId);

        if ($decision) {
            $this->didit->applyDecision($profile, $decision);
            $profile->refresh();
        }

        $message = match ($profile->didit_status) {
            'approved' => 'Tu identidad fue verificada correctamente.',
            'in_review' => 'Tu verificación está en revisión. Te avisaremos cuando termine.',
            'declined' => 'No pudimos verificar tu identidad. Puedes intentarlo de nuevo.',
            'expired' => 'Tu sesión de verificación expiró. Puedes iniciar una nueva.',
            default => 'Estamos confirmando tu verificación. Actualiza la página en unos momentos.',
        };

        $flashType = in_array($profile->didit_status, ['declined', 'expired'], true) ? 'error' : 'success';

        return redirect()
            ->route('panel.dashboard')
            ->with($flashType, $message);
    }

    public function webhook(Request $request): JsonResponse
    {
        if (! $this->didit->webhookConfigured()) {
            return response()->json(['error' => 'Didit webhook no configurado.'], 500);
        }

        $payload = $this->didit->verifyWebhook($request);

        if (! $payload) {
            Log::warning('Didit webhook rejected', [
                'ip' => $request->ip(),
                'has_signature' => $request->hasHeader('X-Signature')
                    || $request->hasHeader('X-Signature-V2')
                    || $request->hasHeader('X-Signature-Simple'),
            ]);

            return response()->json(['error' => 'Firma inválida.'], 401);
        }

        $webhookType = (string) ($payload['webhook_type'] ?? 'status.updated');

        if (! in_array($webhookType, ['status.updated', 'data.updated'], true)) {
            return response()->json(['success' => true, 'ignored' => true]);
        }

        $sessionId = (string) ($payload['session_id'] ?? '');
        $status = (string) ($payload['status'] ?? data_get($payload, 'decision.status', ''));

        if ($sessionId === '' || $status === '') {
            return response()->json(['error' => 'Payload inválido.'], 400);
        }

        $profile = Profile::query()->where('didit_session_id', $sessionId)->first();

        if (! $profile) {
            $vendorData = (string) ($payload['vendor_data'] ?? '');

            if ($vendorData !== '') {
                $profile = Profile::query()->where('user_id', $vendorData)->first();

                if ($profile) {
                    $profile->update(['didit_session_id' => $sessionId]);
                }
            }
        }

        if (! $profile) {
            Log::warning('Didit webhook profile not found', [
                'session_id' => $sessionId,
                'vendor_data' => $payload['vendor_data'] ?? null,
            ]);

            return response()->json(['success' => true, 'ignored' => true]);
        }

        $updated = $this->didit->applyDecision($profile, $payload);

        return response()->json(['success' => true, 'updated' => $updated]);
    }
}
