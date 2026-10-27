<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $brandRules = [
            'vwfs' => ['volkswagen', 'audi', 'seat', 'porsche', 'bentley'],
            'nissan' => ['nissan'],
            'toyota' => ['toyota'],
            'gmfinancial' => ['chevrolet', 'gmc', 'buick', 'cadillac'],
            'ford' => ['ford', 'lincoln'],
            'rci' => ['renault', 'dacia'],
            'stellantis' => ['chrysler', 'jeep', 'dodge', 'ram'],
            'hyundai' => ['hyundai', 'kia'],
            'honda' => ['honda'],
            'mazda' => ['mazda'],
            'acura' => ['acura'],
        ];

        foreach (DB::table('financieras')->orderBy('orden')->get() as $financiera) {
            $aplica = (string) ($financiera->aplica_para ?? '');

            $condition = match (true) {
                str_contains($aplica, 'Autos nuevos') && ! str_contains($aplica, 'seminuevos') => 'new',
                str_contains($aplica, 'Autos seminuevos') && ! str_contains($aplica, 'nuevos') => 'used',
                default => 'any',
            };

            $maxAge = match (true) {
                $financiera->slug === 'afirme' => 10,
                $financiera->tipo === 'banco' => 8,
                $financiera->tipo === 'fintech' => 10,
                default => null,
            };

            DB::table('financieras')->where('id', $financiera->id)->update([
                'vehicle_condition' => $condition,
                'brand_scope' => isset($brandRules[$financiera->slug]) ? json_encode($brandRules[$financiera->slug]) : null,
                'max_vehicle_age' => $maxAge,
                'requires_membership' => $financiera->slug === 'cajapopular',
            ]);
        }
    }

    public function down(): void
    {
        // La migración de columnas elimina estos campos.
    }
};
