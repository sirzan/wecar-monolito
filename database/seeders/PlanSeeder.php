<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
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
    }
}
