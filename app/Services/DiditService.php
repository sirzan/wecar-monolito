<?php

namespace App\Services;

use App\Exceptions\DiditException;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DiditService
{
    private const FEATURE_ARRAYS = [
        'id_verifications',
        'liveness_checks',
        'face_matches',
        'nfc_verifications',
        'poa_verifications',
        'database_validations',
        'aml_screenings',
        'phone_verifications',
        'email_verifications',
    ];

    public function configured(): bool
    {
        return filled(config('services.didit.api_key'))
            && filled(config('services.didit.workflow_id'));
    }

    public function webhookConfigured(): bool
    {
        return filled(config('services.didit.webhook_secret'))
            || filled(config('services.didit.shared_secret'));
    }

    /**
     * @return array{url:string, session_id:string, status:string}
     */
    public function createSession(User $user): array
    {
        if (! $this->configured()) {
            throw new DiditException(422, 'Didit no está configurado.');
        }

        $response = Http::acceptJson()
            ->withHeaders(['x-api-key' => (string) config('services.didit.api_key')])
            ->timeout(20)
            ->post($this->url('/v3/session/'), [
                'workflow_id' => (string) config('services.didit.workflow_id'),
                'vendor_data' => $user->getKey(),
                'callback' => $this->callbackUrl(),
                'callback_method' => 'both',
                'language' => 'es',
                'metadata' => [
                    'user_id' => $user->getKey(),
                ],
                'contact_details' => [
                    'email' => $user->email,
                    'send_notification_emails' => false,
                ],
            ]);

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response);
        }

        $session = $response->json();

        if (! is_array($session) || empty($session['session_id']) || empty($session['url'])) {
            throw new DiditException(502, 'Didit devolvió una sesión inválida.', [
                'body' => Str::limit($response->body(), 800),
            ]);
        }

        $profile = Profile::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['full_name' => $user->name],
        );

        $profile->update([
            'didit_session_id' => $session['session_id'],
            'didit_status' => $this->mapStatus($session['status'] ?? 'Not Started'),
        ]);

        return [
            'url' => (string) $session['url'],
            'session_id' => (string) $session['session_id'],
            'status' => $this->mapStatus($session['status'] ?? 'Not Started'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchDecision(string $sessionId): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-api-key' => (string) config('services.didit.api_key')])
                ->timeout(20)
                ->get($this->url('/v3/session/'.rawurlencode($sessionId).'/decision/'));
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }

        if ($response->failed()) {
            report($this->exceptionFromResponse($response));

            return null;
        }

        $decision = $response->json();

        return is_array($decision) ? $decision : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyDecision(Profile $profile, array $payload): bool
    {
        $status = $this->mapStatus((string) ($payload['status'] ?? data_get($payload, 'decision.status', '')));

        if ($profile->didit_status === $status && ! ($status === 'approved' && $profile->didit_verified_at === null)) {
            return false;
        }

        $decision = is_array($payload['decision'] ?? null) ? $payload['decision'] : $payload;
        $metadata = $profile->metadata ?? [];
        $diditMetadata = array_merge($metadata['didit'] ?? [], [
            'session_id' => $payload['session_id'] ?? $profile->didit_session_id,
            'status' => $payload['status'] ?? $status,
            'normalized_status' => $status,
            'checked_at' => now()->toIso8601String(),
        ]);

        if (filled($verifiedName = data_get($decision, 'id_verifications.0.full_name'))) {
            $diditMetadata['verified_name'] = $verifiedName;
        }

        if ($decision !== $payload || filled(data_get($decision, 'id_verifications'))) {
            $diditMetadata['warnings'] = $this->warningCodes($decision);
        }

        $metadata['didit'] = $diditMetadata;

        $updates = [
            'didit_status' => $status,
            'metadata' => $metadata,
        ];

        if ($status === 'approved') {
            $updates['didit_verified_at'] = $profile->didit_verified_at ?? now();
        } elseif (in_array($status, ['declined', 'expired'], true)) {
            $updates['didit_verified_at'] = null;
        }

        $profile->update($updates);

        return true;
    }

    public function mapStatus(string $status): string
    {
        return match (Str::lower(trim($status))) {
            'approved' => 'approved',
            'declined' => 'declined',
            'in review' => 'in_review',
            'expired', 'abandoned', 'kyc expired' => 'expired',
            default => 'pending',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verifyWebhook(Request $request): ?array
    {
        $payload = $request->getContent();
        $data = json_decode($payload, true);

        if (! is_array($data)) {
            return null;
        }

        $timestamp = (string) $request->header('X-Timestamp', '');

        if ($timestamp === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return null;
        }

        $webhookSecret = (string) (config('services.didit.webhook_secret') ?: config('services.didit.shared_secret'));
        $sharedSecret = (string) (config('services.didit.shared_secret') ?: config('services.didit.webhook_secret'));

        if ($webhookSecret !== '') {
            $canonical = $this->canonicalJson($data);

            foreach (['X-Signature-V2', 'X-Signature-V2-Previous'] as $header) {
                if ($this->signatureMatches($canonical, (string) $request->header($header, ''), $webhookSecret)) {
                    return $data;
                }
            }
        }

        if ($sharedSecret !== '') {
            foreach (['X-Signature', 'X-Signature-Previous'] as $header) {
                if ($this->signatureMatches($payload, (string) $request->header($header, ''), $sharedSecret)) {
                    return $data;
                }
            }

            $simple = implode(':', [
                $data['timestamp'] ?? $timestamp,
                $data['session_id'] ?? '',
                $data['status'] ?? '',
                $data['webhook_type'] ?? '',
            ]);

            foreach (['X-Signature-Simple', 'X-Signature-Simple-Previous'] as $header) {
                if ($this->signatureMatches($simple, (string) $request->header($header, ''), $sharedSecret)) {
                    return $data;
                }
            }
        }

        return null;
    }

    public function callbackUrl(): string
    {
        $configured = (string) config('services.didit.callback_url', '');

        return $configured !== '' ? $configured : route('panel.identity.callback');
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.didit.api_url'), '/').$path;
    }

    private function exceptionFromResponse(Response $response): DiditException
    {
        $body = $response->json();
        $message = is_array($body)
            ? (string) ($body['detail'] ?? $body['message'] ?? data_get($body, 'workflow_id.0') ?? Str::limit($response->body(), 300))
            : Str::limit($response->body(), 300);

        return new DiditException($response->status(), $message, [
            'status' => $response->status(),
            'body' => Str::limit($response->body(), 800),
        ]);
    }

    private function signatureMatches(string $payload, string $signature, string $secret): bool
    {
        $signature = preg_replace('/^sha256=/i', '', trim($signature)) ?? '';

        if ($signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function canonicalJson(array $data): string
    {
        return json_encode(
            $this->sortKeys($this->shortenFloats($data)),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    private function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item) => $this->sortKeys($item), $value);
        }

        ksort($value, SORT_STRING);

        return array_map(fn (mixed $item) => $this->sortKeys($item), $value);
    }

    private function shortenFloats(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->shortenFloats($item), $value);
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array<int, string>
     */
    private function warningCodes(array $decision): array
    {
        return collect(self::FEATURE_ARRAYS)
            ->flatMap(fn (string $key) => collect(data_get($decision, $key, [])))
            ->flatMap(fn (mixed $feature) => is_array($feature) ? ($feature['warnings'] ?? []) : [])
            ->map(fn (mixed $warning) => is_array($warning) ? ($warning['risk'] ?? null) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
