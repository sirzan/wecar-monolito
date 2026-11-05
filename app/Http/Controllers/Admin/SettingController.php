<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\Certifications\CertificationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(private readonly CertificationConfig $certifications) {}

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

        return Inertia::render('Admin/Settings', [
            'settings' => $settings,
            'keys' => self::KEYS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', Rule::in(self::KEYS)],
            'value' => ['required', 'array'],
        ]);

        SiteSetting::put($validated['key'], $validated['value'], $request->user()->getKey());

        return back()->with('success', 'Configuración guardada.');
    }
}
