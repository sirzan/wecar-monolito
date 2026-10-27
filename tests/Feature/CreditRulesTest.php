<?php

use App\Models\Financiera;
use App\Models\User;
use App\Services\CreditOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('only offers brand financing for matching brands and new vehicles', function () {
    $this->seed();

    $offers = app(CreditOfferService::class)->generate([
        'precio' => 400000,
        'enganche' => 80000,
        'plazo' => 48,
        'car_year' => (int) date('Y'),
        'brand' => 'Volkswagen',
        'vehicle_condition' => 'new',
    ], 800, Financiera::all());

    $vw = collect($offers)->firstWhere('slug', 'vwfs');
    $toyota = collect($offers)->firstWhere('slug', 'toyota');

    expect($vw['elegible'])->toBeTrue()
        ->and($toyota['elegible'])->toBeFalse()
        ->and($toyota['motivo'])->toContain('marcas');
});

it('caps the term and includes the service fee in the total', function () {
    $this->seed();

    $financiera = Financiera::query()->where('slug', 'banorte')->firstOrFail();
    $offers = app(CreditOfferService::class)->generate([
        'precio' => 400000,
        'enganche' => 100000,
        'plazo' => 84,
        'car_year' => (int) date('Y') - 2,
        'brand' => 'Nissan',
        'vehicle_condition' => 'used',
    ], 750, collect([$financiera]));

    $offer = $offers[0];

    expect($offer['plazo_efectivo'])->toBe(60)
        ->and($offer['plazo_ajustado'])->toBeTrue()
        ->and($offer['cargo_servicio'])->toBe(7500.0)
        ->and($offer['total'])->toBeGreaterThan($offer['mensualidad'] * 60);
});

it('keeps pawn offers separate from purchase offers', function () {
    $this->seed();

    $response = $this->postJson('/api/credito/evaluar', [
        'auto' => [
            'marca' => 'Nissan',
            'modelo' => 'Sentra',
            'anio' => 2022,
            'precio' => 320000,
            'km' => 35000,
            'tipo' => 'seminuevo',
        ],
        'perfil' => [
            'ingresoMensual' => 40000,
            'enganche' => 80000,
            'plazoMeses' => 48,
            'historialBuro' => 'excelente',
            'tipoEmpleo' => 'fijo',
            'antiguedadLaboralAnios' => 3,
            'deudasMensuales' => 3000,
            'edad' => 30,
        ],
    ]);

    $response->assertOk();

    $data = $response->json();

    expect(collect($data['opcionesRecomendadas'])->pluck('tipo'))->not->toContain('prendaria')
        ->and(collect($data['opcionesPrendarias'])->pluck('tipo'))->toContain('prendaria');
});

it('rejects a selected financiera that is not eligible', function () {
    $this->seed();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $toyota = Financiera::query()->where('slug', 'toyota')->firstOrFail();

    $response = $this->actingAs($user)->postJson('/api/credito/solicitudes', [
        'full_name' => 'Juan Pérez',
        'email' => 'juan@example.com',
        'phone' => '8182838485',
        'calle' => 'Calle 1',
        'colonia' => 'Centro',
        'cp' => '64000',
        'alcaldia' => 'Monterrey',
        'ciudad' => 'Monterrey',
        'pais' => 'México',
        'ocupacion' => 'Empleado',
        'ingreso_mensual' => 50000,
        'banco' => 'BBVA',
        'tipo_cuenta' => 'Nómina',
        'car_brand' => 'Mazda',
        'car_model' => '3',
        'car_year' => 2022,
        'car_price' => 380000,
        'financiera_id' => $toyota->getKey(),
        'financiera_name' => 'Toyota Financial Services',
        'car_condition' => 'used',
        'bureau' => 'bueno',
        'employment' => 'fijo',
        'tenure' => 2,
        'age' => 30,
        'down_payment' => 80000,
        'term_months' => 48,
        'reuse_ine' => false,
        'reuse_domicilio' => false,
        'reuse_estado_cuenta' => false,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('financiera_id');
});
