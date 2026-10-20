<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\CarVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    private const RESOURCES = ['brands', 'models', 'versions', 'statuses'];

    public function index(Request $request, string $resource): Response
    {
        abort_unless(in_array($resource, self::RESOURCES, true), 404);

        return Inertia::render('Admin/Catalogs', [
            'resource' => $resource,
            'items' => $this->items($resource),
            'options' => [
                'brands' => CarBrand::query()->orderBy('name')->get(['id', 'name']),
                'models' => CarModel::query()->with('brand:id,name')->orderBy('name')->get(['id', 'brand_id', 'name']),
            ],
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        abort_unless(in_array($resource, self::RESOURCES, true), 404);

        $data = $this->validated($request, $resource);

        match ($resource) {
            'brands' => CarBrand::query()->create($data),
            'models' => CarModel::query()->create($data),
            'versions' => CarVersion::query()->create($data),
            'statuses' => CarStatus::query()->create($data),
        };

        return back()->with('success', 'Registro creado.');
    }

    public function update(Request $request, string $resource, string $id): RedirectResponse
    {
        abort_unless(in_array($resource, self::RESOURCES, true), 404);

        $model = $this->find($resource, $id);
        $model->update($this->validated($request, $resource, $id));

        return back()->with('success', 'Registro actualizado.');
    }

    public function destroy(string $resource, string $id): RedirectResponse
    {
        abort_unless(in_array($resource, self::RESOURCES, true), 404);

        $this->find($resource, $id)->delete();

        return back()->with('success', 'Registro eliminado.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(string $resource): array
    {
        return match ($resource) {
            'brands' => CarBrand::query()->withCount('models')->orderBy('name')->get()
                ->map(fn (CarBrand $brand) => [
                    'id' => $brand->getKey(),
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'logo' => $brand->logo,
                    'sort_order' => $brand->sort_order,
                    'models_count' => $brand->models_count,
                ])->all(),
            'models' => CarModel::query()->with('brand:id,name')->orderBy('name')->get()
                ->map(fn (CarModel $model) => [
                    'id' => $model->getKey(),
                    'brand_id' => $model->brand_id,
                    'brand_name' => $model->brand?->name,
                    'name' => $model->name,
                    'slug' => $model->slug,
                ])->all(),
            'versions' => CarVersion::query()->with('model.brand')->orderBy('name')->get()
                ->map(fn (CarVersion $version) => [
                    'id' => $version->getKey(),
                    'model_id' => $version->model_id,
                    'model_name' => $version->model?->name,
                    'brand_name' => $version->model?->brand?->name,
                    'name' => $version->name,
                    'year' => $version->year,
                ])->all(),
            'statuses' => CarStatus::query()->orderBy('sort_order')->get()
                ->map(fn (CarStatus $status) => [
                    'id' => $status->getKey(),
                    'name' => $status->name,
                    'label' => $status->label,
                    'sort_order' => $status->sort_order,
                ])->all(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $resource, ?string $id = null): array
    {
        return match ($resource) {
            'brands' => tap($request->validate([
                'name' => ['required', 'string', 'max:120'],
                'slug' => ['nullable', 'string', 'max:140', Rule::unique('car_brands', 'slug')->ignore($id)],
                'logo' => ['nullable', 'string', 'max:500'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ]), function (array &$data) {
                $data['slug'] = empty($data['slug']) ? Str::slug($data['name']) : $data['slug'];
                $data['sort_order'] = $data['sort_order'] ?? 0;
            }),
            'models' => tap($request->validate([
                'brand_id' => ['required', 'uuid', 'exists:car_brands,id'],
                'name' => ['required', 'string', 'max:120'],
                'slug' => ['nullable', 'string', 'max:140', Rule::unique('car_models', 'slug')->where('brand_id', $request->input('brand_id'))->ignore($id)],
            ]), function (array &$data) {
                $data['slug'] = empty($data['slug']) ? Str::slug($data['name']) : $data['slug'];
            }),
            'versions' => $request->validate([
                'model_id' => ['required', 'uuid', 'exists:car_models,id'],
                'name' => ['required', 'string', 'max:160'],
                'year' => ['nullable', 'string', 'max:20'],
            ]),
            'statuses' => tap($request->validate([
                'name' => ['required', 'string', 'max:60', Rule::unique('car_statuses', 'name')->ignore($id)],
                'label' => ['required', 'string', 'max:80'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ]), function (array &$data) {
                $data['sort_order'] = $data['sort_order'] ?? 0;
            }),
        };
    }

    private function find(string $resource, string $id): CarBrand|CarModel|CarVersion|CarStatus
    {
        return match ($resource) {
            'brands' => CarBrand::query()->findOrFail($id),
            'models' => CarModel::query()->findOrFail($id),
            'versions' => CarVersion::query()->findOrFail($id),
            'statuses' => CarStatus::query()->findOrFail($id),
        };
    }
}
