<?php

namespace Database\Factories;

use App\Models\CreditApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditApplication>
 */
class CreditApplicationFactory extends Factory
{
    protected $model = CreditApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'folio' => 'WC-'.Str::upper(Str::random(8)),
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('81########'),
            'birthdate' => fake()->dateTimeBetween('-50 years', '-20 years')->format('Y-m-d'),
            'rfc' => Str::upper(Str::random(13)),
            'curp' => Str::upper(Str::random(18)),
            'calle' => fake()->streetAddress(),
            'colonia' => fake()->cityPrefix(),
            'cp' => '64000',
            'alcaldia' => 'Monterrey',
            'ciudad' => 'Monterrey',
            'pais' => 'México',
            'ocupacion' => 'Ingeniero',
            'empresa' => 'Tech Corp',
            'antiguedad' => '3 años',
            'ingreso_mensual' => 45000,
            'deuda_mensual' => 5000,
            'banco' => 'BBVA',
            'tipo_cuenta' => 'Nómina',
            'car_brand' => 'Toyota',
            'car_model' => 'Corolla',
            'car_version' => 'XLE',
            'car_year' => 2022,
            'car_price' => 350000,
            'car_km' => 35000,
            'financiera_name' => 'BBVA Auto',
            'score' => 720,
            'risk_level' => 'bajo',
            'down_payment' => 70000,
            'term_months' => 48,
            'monthly_payment' => 7500,
            'rate' => 12.99,
            'cat' => 20.0,
            'total_to_pay' => 430000,
            'status' => 'en_revision',
            'submitted_at' => now(),
        ];
    }
}
