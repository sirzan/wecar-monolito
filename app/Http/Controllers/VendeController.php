<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\CarCatalog\CarVersionService;
use App\Support\AgencyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendeController extends Controller
{
    public function store(Request $request, CarVersionService $carVersions): RedirectResponse
    {
        $validated = $request->validate([
            'brand_id' => ['required', 'uuid', 'exists:car_brands,id'],
            'model_id' => ['required', 'uuid', 'exists:car_models,id'],
            'version_id' => ['required', 'uuid', 'exists:car_versions,id'],
            'year' => ['required', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'km' => ['required', 'integer', 'min:0', 'max:2000000'],
            'price' => ['required', 'numeric', 'min:1000', 'max:100000000'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'transmission' => ['nullable', Rule::in(['Automática', 'Manual', 'CVT'])],
            'fuel' => ['nullable', Rule::in(['Gasolina', 'Diésel', 'Híbrido'])],
            'body' => ['nullable', Rule::in(['Sedán', 'SUV', 'Hatchback', 'Pickup'])],
            'color' => ['nullable', 'string', 'max:40'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'accepts_trade' => ['boolean'],
            'trade_preference' => ['nullable', 'string', 'max:160'],
            'vin' => ['nullable', 'string', 'max:40'],
            'serial_number' => ['nullable', 'string', 'max:40'],
            'plates' => ['nullable', 'string', 'max:20'],
            'plan_key' => ['nullable', 'string', 'exists:plans,key_name'],
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $isAdmin = $request->user()->hasAdminPrivileges();

        $agency = app(AgencyContext::class)->current($request);
        $agency = $agency && $request->user()->belongsToAgency($agency) ? $agency : null;

        if (! $isAdmin && $agency && $agency->cars()->count() >= $agency->max_cars) {
            throw ValidationException::withMessages([
                'plan_key' => 'La agencia alcanzó su límite de autos. Actualiza el plan para publicar más.',
            ]);
        }

        $plan = isset($validated['plan_key'])
            ? Plan::query()->where('key_name', $validated['plan_key'])->where('type', 'publication')->first()
            : null;

        if (isset($validated['plan_key']) && ! $plan) {
            throw ValidationException::withMessages([
                'plan_key' => 'El plan seleccionado no es válido para publicar un auto.',
            ]);
        }

        $requiresPayment = ! $isAdmin && $plan !== null && (float) $plan->price > 0;
        $status = CarStatus::query()->where('name', $requiresPayment ? 'pendiente' : 'en_venta')->first();

        $brand = CarBrand::query()->find($validated['brand_id']);
        $model = CarModel::query()->find($validated['model_id']);

        if (! $model || $model->brand_id !== $brand?->getKey()) {
            throw ValidationException::withMessages([
                'model_id' => 'El modelo no pertenece a la marca seleccionada.',
            ]);
        }

        $version = $carVersions->validateForModel($model, $validated['version_id'], (int) $validated['year']);

        if (! $version) {
            throw ValidationException::withMessages([
                'version_id' => 'La versión no pertenece al modelo o año seleccionado.',
            ]);
        }

        $baseSlug = Str::slug(implode('-', array_filter([
            $validated['year'],
            $brand?->name,
            $model?->name,
        ])));

        $car = Car::query()->create([
            ...collect($validated)->except(['images', 'plan_key', 'version_id'])->all(),
            'version_id' => $version->getKey(),
            'owner_id' => $request->user()->getKey(),
            'agency_id' => $agency?->getKey(),
            'seller_id' => $agency ? $request->user()->getKey() : null,
            'status_id' => $status?->getKey(),
            'plan' => $plan?->key_name,
            'slug' => $baseSlug.'-'.Str::lower(Str::random(6)),
            'is_published' => true,
            'published_at' => now(),
        ]);

        $gallery = [];
        foreach ($request->file('images', []) as $index => $image) {
            $path = $image->store("cars/{$car->getKey()}", 'public');
            $url = Storage::disk('public')->url($path);
            $gallery[] = $url;

            if ($index === 0) {
                $car->image = $url;
            }
        }

        $car->gallery = $gallery;
        $car->save();

        $subscription = null;

        if ($plan && ! $isAdmin) {
            $subscription = Subscription::query()->create([
                'user_id' => $request->user()->getKey(),
                'kind' => 'publication',
                'plan_id' => $plan->getKey(),
                'car_id' => $car->getKey(),
                'status' => $requiresPayment ? 'pending' : 'active',
                'billing_cycle' => 'total',
                'amount' => (float) $plan->price,
                'starts_at' => $requiresPayment ? null : now(),
                'ends_at' => $requiresPayment ? null : now()->addWeeks((int) $plan->duration_weeks),
            ]);
        }

        if ($requiresPayment && $subscription) {
            return redirect()
                ->route('checkout.show', $subscription)
                ->with('success', 'Tu auto fue creado. Completa el pago para publicarlo.');
        }

        return redirect()
            ->route('autos.show', $car->slug)
            ->with('success', 'Tu auto se publicó correctamente.');
    }
}
