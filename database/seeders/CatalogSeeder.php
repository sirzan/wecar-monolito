<?php

namespace Database\Seeders;

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\Plan;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $brandsPath = database_path('data/brands.json');
        $modelsPath = database_path('data/models.json');

        $brandMap = [];

        if (file_exists($brandsPath)) {
            foreach (json_decode(file_get_contents($brandsPath), true) as $brand) {
                $model = CarBrand::query()->firstOrCreate(
                    ['slug' => Str::slug($brand['name'])],
                    ['name' => $brand['name']],
                );
                $brandMap[$brand['id']] = $model->getKey();
            }
        }

        if (file_exists($modelsPath)) {
            foreach (json_decode(file_get_contents($modelsPath), true) as $model) {
                if (! isset($brandMap[$model['brand_id']])) {
                    continue;
                }

                CarModel::query()->firstOrCreate(
                    [
                        'brand_id' => $brandMap[$model['brand_id']],
                        'slug' => Str::slug($model['name']),
                    ],
                    ['name' => $model['name']],
                );
            }
        }

        $statuses = [
            ['name' => 'pendiente', 'label' => 'Pendiente', 'sort_order' => 1],
            ['name' => 'en_venta', 'label' => 'En Venta', 'sort_order' => 2],
            ['name' => 'promocion', 'label' => 'Promoción', 'sort_order' => 3],
            ['name' => 'nuevo', 'label' => 'Nuevo', 'sort_order' => 4],
            ['name' => 'pausado', 'label' => 'Pausado', 'sort_order' => 5],
            ['name' => 'vendido', 'label' => 'Vendido', 'sort_order' => 6],
            ['name' => 'cancelado', 'label' => 'Cancelado', 'sort_order' => 7],
        ];

        foreach ($statuses as $status) {
            CarStatus::query()->updateOrCreate(['name' => $status['name']], $status);
        }

        $plans = [
            ['key_name' => 'basico', 'type' => 'publication', 'name' => 'Básico', 'price' => 0, 'duration' => '30 días', 'duration_weeks' => 4, 'max_cars' => 1, 'sort_order' => 1],
            ['key_name' => 'destacado', 'type' => 'publication', 'name' => 'Destacado', 'price' => 499, 'duration' => '30 días', 'duration_weeks' => 4, 'max_cars' => 5, 'highlight' => true, 'sort_order' => 2],
            ['key_name' => 'premium', 'type' => 'publication', 'name' => 'Premium', 'price' => 999, 'duration' => '30 días', 'duration_weeks' => 4, 'max_cars' => 15, 'sort_order' => 3],
            ['key_name' => 'agencia', 'type' => 'publication', 'name' => 'Agencia', 'price' => 1999, 'duration' => '30 días', 'duration_weeks' => 4, 'max_cars' => 30, 'sort_order' => 4],
            ['key_name' => 'membresia_ext_499', 'type' => 'membership', 'name' => 'Membresía Búsqueda Externa', 'price' => 499, 'duration' => '30 días', 'duration_weeks' => 4, 'max_cars' => 0, 'sort_order' => 5],
            ['key_name' => 'search_tokens_5', 'type' => 'tokens', 'name' => '5 búsquedas externas', 'price' => 49, 'duration' => 'Sin vencimiento', 'duration_weeks' => 0, 'max_cars' => 0, 'token_amount' => 5, 'sort_order' => 6],
            ['key_name' => 'search_tokens_10', 'type' => 'tokens', 'name' => '10 búsquedas externas', 'price' => 79, 'duration' => 'Sin vencimiento', 'duration_weeks' => 0, 'max_cars' => 0, 'token_amount' => 10, 'highlight' => true, 'sort_order' => 7],
            ['key_name' => 'search_tokens_15', 'type' => 'tokens', 'name' => '15 búsquedas externas', 'price' => 109, 'duration' => 'Sin vencimiento', 'duration_weeks' => 0, 'max_cars' => 0, 'token_amount' => 15, 'sort_order' => 8],
            ['key_name' => 'search_tokens_20', 'type' => 'tokens', 'name' => '20 búsquedas externas', 'price' => 139, 'duration' => 'Sin vencimiento', 'duration_weeks' => 0, 'max_cars' => 0, 'token_amount' => 20, 'sort_order' => 9],
            ['key_name' => 'search_tokens_30', 'type' => 'tokens', 'name' => '30 búsquedas externas', 'price' => 189, 'duration' => 'Sin vencimiento', 'duration_weeks' => 0, 'max_cars' => 0, 'token_amount' => 30, 'sort_order' => 10],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['key_name' => $plan['key_name']], $plan);
        }

        SiteSetting::put('payments', [
            'stripe_enabled' => false,
            'paypal_enabled' => false,
            'spei_enabled' => false,
            'tokens_enabled' => false,
            'membership_enabled' => false,
        ]);

        SiteSetting::put('contact_info', [
            'email' => 'hola@wecar.mx',
            'phone' => '+52 81 1234 5678',
            'whatsapp' => '+52 81 1234 5678',
            'address' => 'Av. Constitución 1500, Monterrey, NL',
            'hours' => 'L-V 9:00–18:00',
        ]);

        SiteSetting::put('about_page', [
            'title' => 'Sobre Wecar',
            'subtitle' => 'Conectamos compradores con vendedores verificados.',
            'mission' => 'Construyendo confianza en la industria automotriz.',
            'vision' => 'Un auto Wecar en cada hogar de México.',
            'values' => 'Integridad, transparencia y cercanía.',
        ]);

        SiteSetting::put('footer_config', [
            'copyright' => '© '.date('Y').' Wecar. Todos los derechos reservados.',
            'legal_links_label' => 'Legal',
            'show_newsletter' => false,
        ]);

        SiteSetting::put('warranty_config', [
            'enabled' => true,
            'title' => 'Garantía Wecar',
            'description' => 'Protección integral para tu compra.',
            'contact_email' => 'garantia@wecar.mx',
        ]);
    }
}
