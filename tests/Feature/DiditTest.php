<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function diditCanonical(array $data): string
{
    $normalize = function (mixed $value) use (&$normalize): mixed {
        if (! is_array($value)) {
            if (is_float($value) && floor($value) === $value) {
                return (int) $value;
            }

            return $value;
        }

        if (array_is_list($value)) {
            return array_map($normalize, $value);
        }

        ksort($value, SORT_STRING);

        return array_map($normalize, $value);
    };

    return json_encode($normalize($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function diditUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function diditWebhookPayload(Profile $profile, string $status, ?string $secret = 'didit-webhook-secret', ?int $timestamp = null): array
{
    $timestamp ??= time();
    $payload = [
        'event_id' => (string) str()->uuid(),
        'webhook_type' => 'status.updated',
        'timestamp' => $timestamp,
        'session_id' => $profile->didit_session_id,
        'status' => $status,
        'vendor_data' => $profile->user_id,
        'decision' => [
            'session_id' => $profile->didit_session_id,
            'status' => $status,
            'id_verifications' => [[
                'status' => $status === 'Approved' ? 'Approved' : 'Declined',
                'full_name' => 'Ana Verificada',
                'warnings' => $status === 'Declined' ? [['risk' => 'LOW_LIVENESS_SCORE']] : [],
            ]],
        ],
    ];

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return [
        'body' => $body,
        'timestamp' => (string) $timestamp,
        'signature' => hash_hmac('sha256', diditCanonical($payload), (string) $secret),
    ];
}

it('creates a v3 didit session and stores the pending profile', function () {
    config()->set('services.didit.api_key', 'didit-key');
    config()->set('services.didit.workflow_id', '145c0241-c409-4f20-a4c5-552218e11bbf');
    config()->set('services.didit.callback_url', 'https://wecar.test/panel/verificacion/retorno');

    Http::fake([
        'https://verification.didit.me/v3/session/' => Http::response([
            'session_id' => '4c5c7f3a-1f82-4f3b-8d8e-1a8d2d2f9b7a',
            'url' => 'https://verify.didit.me/session/test',
            'status' => 'Not Started',
        ], 201),
    ]);

    $user = diditUser();

    $this->actingAs($user)
        ->postJson('/api/didit/session')
        ->assertOk()
        ->assertJson([
            'url' => 'https://verify.didit.me/session/test',
            'session_id' => '4c5c7f3a-1f82-4f3b-8d8e-1a8d2d2f9b7a',
            'status' => 'pending',
        ]);

    Http::assertSent(fn ($request) => $request->url() === 'https://verification.didit.me/v3/session/'
        && $request->hasHeader('x-api-key', 'didit-key')
        && $request['workflow_id'] === '145c0241-c409-4f20-a4c5-552218e11bbf'
        && $request['vendor_data'] === $user->getKey()
        && $request['callback'] === 'https://wecar.test/panel/verificacion/retorno'
        && $request['callback_method'] === 'both');

    $profile = Profile::query()->where('user_id', $user->getKey())->firstOrFail();

    expect($profile->didit_session_id)->toBe('4c5c7f3a-1f82-4f3b-8d8e-1a8d2d2f9b7a')
        ->and($profile->didit_status)->toBe('pending');
});

it('returns a controlled error when didit rejects the session', function () {
    config()->set('services.didit.api_key', 'didit-key');
    config()->set('services.didit.workflow_id', 'workflow');

    Http::fake([
        'https://verification.didit.me/v3/session/' => Http::response(['message' => 'Not Found'], 404),
    ]);

    $this->actingAs(diditUser())
        ->postJson('/api/didit/session')
        ->assertStatus(502)
        ->assertJson(['error' => 'No se pudo iniciar la verificación de identidad.']);
});

it('accepts signed v2 webhooks and maps approved status', function () {
    config()->set('services.didit.webhook_secret', 'didit-webhook-secret');

    $user = diditUser();
    $profile = Profile::query()->create([
        'user_id' => $user->getKey(),
        'full_name' => $user->name,
        'didit_session_id' => 'session-v2-1',
        'didit_status' => 'pending',
    ]);
    $webhook = diditWebhookPayload($profile, 'Approved');

    $this->call('POST', '/api/webhooks/didit', [], [], [], [
        'HTTP_X_SIGNATURE_V2' => $webhook['signature'],
        'HTTP_X_TIMESTAMP' => $webhook['timestamp'],
        'CONTENT_TYPE' => 'application/json',
    ], $webhook['body'])->assertOk()->assertJson(['updated' => true]);

    $profile->refresh();

    expect($profile->didit_status)->toBe('approved')
        ->and($profile->didit_verified_at)->not->toBeNull()
        ->and(data_get($profile->metadata, 'didit.warning_codes'))->toBeNull()
        ->and(data_get($profile->metadata, 'didit.warnings'))->toBe([]);
});

it('maps in review webhooks without marking the profile verified', function () {
    config()->set('services.didit.webhook_secret', 'didit-webhook-secret');

    $user = diditUser();
    $profile = Profile::query()->create([
        'user_id' => $user->getKey(),
        'full_name' => $user->name,
        'didit_session_id' => 'session-review-1',
        'didit_status' => 'pending',
    ]);
    $webhook = diditWebhookPayload($profile, 'In Review');

    $this->call('POST', '/webhooks/didit', [], [], [], [
        'HTTP_X_SIGNATURE_V2' => $webhook['signature'],
        'HTTP_X_TIMESTAMP' => $webhook['timestamp'],
        'CONTENT_TYPE' => 'application/json',
    ], $webhook['body'])->assertOk();

    expect($profile->fresh()->didit_status)->toBe('in_review')
        ->and($profile->fresh()->didit_verified_at)->toBeNull();
});

it('rejects webhooks with a stale timestamp', function () {
    config()->set('services.didit.webhook_secret', 'didit-webhook-secret');

    $user = diditUser();
    $profile = Profile::query()->create([
        'user_id' => $user->getKey(),
        'full_name' => $user->name,
        'didit_session_id' => 'session-stale-1',
        'didit_status' => 'pending',
    ]);
    $webhook = diditWebhookPayload($profile, 'Approved', timestamp: time() - 301);

    $this->call('POST', '/webhooks/didit', [], [], [], [
        'HTTP_X_SIGNATURE_V2' => $webhook['signature'],
        'HTTP_X_TIMESTAMP' => $webhook['timestamp'],
        'CONTENT_TYPE' => 'application/json',
    ], $webhook['body'])->assertStatus(401);
});

it('syncs the decision on the didit callback', function () {
    config()->set('services.didit.api_key', 'didit-key');
    config()->set('services.didit.workflow_id', 'workflow');

    Http::fake([
        'https://verification.didit.me/v3/session/session-callback-1/decision/' => Http::response([
            'session_id' => 'session-callback-1',
            'status' => 'Approved',
            'id_verifications' => [[
                'status' => 'Approved',
                'full_name' => 'Ana Verificada',
                'warnings' => [],
            ]],
        ]),
    ]);

    $user = diditUser();
    Profile::query()->create([
        'user_id' => $user->getKey(),
        'full_name' => $user->name,
        'didit_session_id' => 'session-callback-1',
        'didit_status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get('/panel/verificacion/retorno?verificationSessionId=session-callback-1&status=Approved')
        ->assertRedirect('/panel');

    $profile = Profile::query()->where('user_id', $user->getKey())->firstOrFail();

    expect($profile->didit_status)->toBe('approved')
        ->and($profile->didit_verified_at)->not->toBeNull()
        ->and(data_get($profile->metadata, 'didit.verified_name'))->toBe('Ana Verificada');
});

it('does not sync a callback that belongs to another session', function () {
    $user = diditUser();
    Profile::query()->create([
        'user_id' => $user->getKey(),
        'full_name' => $user->name,
        'didit_session_id' => 'session-own',
        'didit_status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get('/panel/verificacion/retorno?verificationSessionId=session-other&status=Approved')
        ->assertRedirect('/panel')
        ->assertSessionHas('error');

    expect(Profile::query()->where('user_id', $user->getKey())->firstOrFail()->didit_status)->toBe('pending');
});
