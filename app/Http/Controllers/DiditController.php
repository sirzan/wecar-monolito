<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DiditController extends Controller
{
    public function createSession(Request $request): JsonResponse
    {
        $apiKey = config('services.didit.api_key');
        $workflowId = config('services.didit.workflow_id');

        if (! $apiKey || ! $workflowId) {
            return response()->json(['error' => 'Didit no está configurado.'], 422);
        }

        $apiUrl = rtrim((string) config('services.didit.api_url'), '/');

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'accept' => 'application/json',
        ])->post($apiUrl.'/v2/session/', [
            'workflow_id' => $workflowId,
            'vendor_data' => $request->user()->getKey(),
            'callback' => route('panel.dashboard'),
        ]);

        if ($response->failed()) {
            report(new \RuntimeException('Didit session failed: '.$response->body()));

            return response()->json(['error' => 'No se pudo iniciar la verificación.'], 502);
        }

        $session = $response->json();

        Profile::query()->updateOrCreate(
            ['user_id' => $request->user()->getKey()],
            [
                'full_name' => $request->user()->name,
                'didit_session_id' => $session['session_id'] ?? null,
                'didit_status' => 'pending',
            ],
        );

        return response()->json([
            'url' => $session['url'] ?? null,
            'session_id' => $session['session_id'] ?? null,
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) config('services.didit.shared_secret');

        if ($secret === '') {
            return response()->json(['error' => 'Didit webhook no configurado.'], 500);
        }

        $payload = $request->getContent();
        $signature = (string) ($request->header('x-signature') ?? $request->header('x-webhook-signature'));
        $signature = preg_replace('/^sha256=/', '', $signature) ?? '';

        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Firma inválida.'], 401);
        }

        $data = json_decode($payload, true);

        if (! is_array($data) || empty($data['session_id']) || empty($data['status'])) {
            return response()->json(['error' => 'Payload inválido.'], 400);
        }

        $profile = Profile::query()
            ->where('didit_session_id', $data['session_id'])
            ->first();

        if (! $profile) {
            return response()->json(['error' => 'Sesión no encontrada.'], 404);
        }

        $status = strtolower((string) $data['status']);

        $profile->update([
            'didit_status' => $status,
            'didit_verified_at' => $status === 'approved' ? now() : null,
        ]);

        return response()->json(['success' => true]);
    }
}
