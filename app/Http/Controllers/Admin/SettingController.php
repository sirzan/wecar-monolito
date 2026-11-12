<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\Certifications\CertificationConfig;
use App\Services\PaymentMethodsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        private readonly CertificationConfig $certifications,
        private readonly PaymentMethodsService $paymentMethods,
    ) {}

    private const KEYS = [
        'payments', 'contact_info', 'about_page', 'footer_config',
        'warranty_config', 'privacy_policy', 'terms_conditions', 'certification_config',
    ];

    public function index(): Response
    {
        $settings = [];

        foreach (self::KEYS as $key) {
            $settings[$key] = $key === 'certification_config'
                ? $this->certifications->settings()
                : SiteSetting::get($key, []);
        }

        $paymentSettings = is_array($settings['payments'] ?? null) ? $settings['payments'] : [];

        return Inertia::render('Admin/Settings', [
            'settings' => $settings,
            'keys' => self::KEYS,
            'paymentStatus' => $this->paymentMethods->adminStatus($paymentSettings),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', Rule::in(self::KEYS)],
            'value' => ['required', 'array'],
        ]);

        if ($validated['key'] === 'payments') {
            $payments = $validated['value'];

            if (filled($payments['spei_clabe'] ?? null)
                && preg_match('/^\d{18}$/', (string) $payments['spei_clabe']) !== 1) {
                throw ValidationException::withMessages([
                    'value.spei_clabe' => 'La CLABE SPEI debe contener exactamente 18 dígitos.',
                ]);
            }
        }

        SiteSetting::put($validated['key'], $validated['value'], $request->user()->getKey());

        return back()->with('success', 'Configuración guardada.');
    }
}
