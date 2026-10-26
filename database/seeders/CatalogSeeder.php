<?php

namespace Database\Seeders;

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
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
