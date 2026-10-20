<?php

use App\Models\CarBrand;
use App\Models\CarModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Catálogo completo de marcas y modelos para el mercado mexicano.
     *
     * @var array<int, array{brand: string, models: string[]}>
     */
    private array $catalogs = [
        [
            'brand' => 'Nissan',
            'models' => [
                'Versa', 'Sentra', 'March', 'Kicks', 'X-Trail',
                'Frontier', 'NP300', 'Note', 'Altima', 'Pathfinder', 'Murano',
            ],
        ],
        [
            'brand' => 'Chevrolet',
            'models' => [
                'Aveo', 'Onix', 'Tracker', 'Beat', 'Spark', 'Cavalier',
                'Captiva', 'Trax', 'Equinox', 'Tahoe', 'Suburban',
                'Cheyenne', 'Silverado', 'Tornado',
            ],
        ],
        [
            'brand' => 'Volkswagen',
            'models' => [
                'Vento', 'Jetta', 'Virtus', 'Polo', 'Taos', 'T-Cross',
                'Tiguan', 'Teramont', 'Saveiro', 'Nivus', 'Crossfox', 'Gol',
            ],
        ],
        [
            'brand' => 'Toyota',
            'models' => [
                'Corolla', 'Yaris', 'Hilux', 'Tacoma', 'RAV4',
                'Highlander', 'Camry', 'Avanza', 'Corolla Cross',
                'Prius', '4Runner', 'Sequoia', 'Tundra',
            ],
        ],
        [
            'brand' => 'Kia',
            'models' => [
                'Rio', 'Forte', 'K3', 'Sportage', 'Seltos',
                'Soul', 'Sorento', 'Carnival', 'Picanto', 'Stonic',
            ],
        ],
        [
            'brand' => 'Hyundai',
            'models' => [
                'Grand i10', 'Accent', 'Elantra', 'Tucson', 'Creta',
                'Santa Fe', 'Kona', 'Sonata', 'Venue', 'Palisade',
            ],
        ],
        [
            'brand' => 'Mazda',
            'models' => [
                '3', '2', 'CX-30', 'CX-3', 'CX-5',
                'CX-50', 'CX-7', 'CX-9', 'MX-5', 'BT-50',
            ],
        ],
        [
            'brand' => 'Ford',
            'models' => [
                'Ranger', 'Lobo', 'F-150', 'Bronco', 'Bronco Sport',
                'Escape', 'Edge', 'Explorer', 'Expedition', 'Mustang',
                'Maverick', 'Territory',
            ],
        ],
        [
            'brand' => 'Honda',
            'models' => [
                'Civic', 'City', 'Fit', 'CR-V', 'HR-V',
                'BR-V', 'Accord', 'Pilot', 'Odyssey',
            ],
        ],
        [
            'brand' => 'Mitsubishi',
            'models' => [
                'L200', 'Outlander', 'Mirage', 'ASX',
                'Eclipse Cross', 'Montero',
            ],
        ],
        [
            'brand' => 'Jeep',
            'models' => [
                'Compass', 'Renegade', 'Cherokee',
                'Grand Cherokee', 'Wrangler', 'Gladiator',
            ],
        ],
        [
            'brand' => 'Ram',
            'models' => [
                '700', '1500', '2500', '3500', 'ProMaster', 'Rampage',
            ],
        ],
        [
            'brand' => 'Dodge',
            'models' => [
                'Attitude', 'Journey', 'Charger', 'Challenger', 'Durango',
            ],
        ],
        [
            'brand' => 'Suzuki',
            'models' => [
                'Swift', 'Ignis', 'Vitara', 'S-Cross',
                'Jimny', 'Ertiga', 'Grand Vitara', 'Baleno',
            ],
        ],
        [
            'brand' => 'Renault',
            'models' => [
                'Kwid', 'Stepway', 'Logan', 'Sandero',
                'Duster', 'Oroch', 'Captur', 'Koleos',
            ],
        ],
        [
            'brand' => 'Seat',
            'models' => [
                'Ibiza', 'Leon', 'Arona', 'Ateca', 'Tarraco',
            ],
        ],
        [
            'brand' => 'Mg',
            'models' => [
                'MG 5', 'MG GT', 'ZS', 'HS', 'RX5', 'RX8',
            ],
        ],
        [
            'brand' => 'Chirey',
            'models' => [
                'Tiggo 2', 'Tiggo 4', 'Tiggo 7 Pro',
                'Tiggo 8 Pro', 'Arrizo 5', 'Arrizo 6',
            ],
        ],
        [
            'brand' => 'Jac',
            'models' => [
                'JS3', 'JS4', 'JS8', 'T6', 'T8', 'Sei4', 'Sei7',
            ],
        ],
        [
            'brand' => 'Gwm',
            'models' => [
                'Tank 300', 'Tank 500', 'Haval H6',
                'Haval Jolion', 'Poer', 'Ora',
            ],
        ],
        [
            'brand' => 'Baic',
            'models' => [
                'X35', 'X55', 'X25', 'U5 Plus', 'BJ40',
            ],
        ],
        ['brand' => 'Peugeot', 'models' => []],
        ['brand' => 'Fiat', 'models' => []],
        ['brand' => 'Bmw', 'models' => []],
        ['brand' => 'Mercedes-Benz', 'models' => []],
        ['brand' => 'Audi', 'models' => []],
        ['brand' => 'Acura', 'models' => []],
        ['brand' => 'Lincoln', 'models' => []],
        ['brand' => 'Cadillac', 'models' => []],
        ['brand' => 'Buick', 'models' => []],
        ['brand' => 'Gmc', 'models' => []],
        ['brand' => 'Mini', 'models' => []],
        ['brand' => 'Volvo', 'models' => []],
        ['brand' => 'Subaru', 'models' => []],
        ['brand' => 'Land Rover', 'models' => []],
        ['brand' => 'Jaguar', 'models' => []],
        ['brand' => 'Porsche', 'models' => []],
        ['brand' => 'Lexus', 'models' => []],
        ['brand' => 'Infiniti', 'models' => []],
        ['brand' => 'Alfa Romeo', 'models' => []],
    ];

    public function up(): void
    {
        foreach ($this->catalogs as $index => $item) {
            $brandName = $item['brand'];
            $brandSlug = Str::slug($brandName);

            $brand = CarBrand::query()->firstOrCreate(
                ['slug' => $brandSlug],
                [
                    'name' => $brandName,
                    'sort_order' => $index + 1,
                ],
            );

            foreach ($item['models'] as $mIndex => $modelName) {
                $modelSlug = Str::slug($modelName);

                CarModel::query()->firstOrCreate(
                    [
                        'brand_id' => $brand->getKey(),
                        'slug' => $modelSlug,
                    ],
                    [
                        'name' => $modelName,
                        'sort_order' => $mIndex + 1,
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        foreach ($this->catalogs as $item) {
            $brandSlug = Str::slug($item['brand']);
            $brand = CarBrand::query()->where('slug', $brandSlug)->first();

            if (! $brand) {
                continue;
            }

            foreach ($item['models'] as $modelName) {
                $modelSlug = Str::slug($modelName);
                CarModel::query()
                    ->where('brand_id', $brand->getKey())
                    ->where('slug', $modelSlug)
                    ->delete();
            }

            // Si la marca no tiene más modelos ni autos asignados, se elimina
            if ($brand->models()->count() === 0) {
                $brand->delete();
            }
        }
    }
};
