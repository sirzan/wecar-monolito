<?php

return [
    'timezone' => 'America/Monterrey',

    'service' => 'Revisión mecánica y legal',
    'points' => 160,

    'base_price' => 1390.00,
    'tax_rate' => 0.16,
    'currency' => 'MXN',

    'duration_minutes' => 60,
    'slot_interval_minutes' => 60,
    'capacity_per_slot' => 1,
    'min_notice_hours' => 24,

    'admin_email' => 'hola@wecar.mx',

    'workshop' => [
        'name' => 'Taller Motorevs',
        'address' => 'Dr. José Ma. Coss 548, Centro, 64000 Monterrey, N.L.',
        'note' => 'La revisión se realiza en el taller Motorevs.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Horario inicial (editable desde /admin/configuracion)
    |--------------------------------------------------------------------------
    | 0 = domingo, 1 = lunes ... 6 = sábado.
    */
    'business_hours' => [
        0 => ['enabled' => false, 'start' => '09:00', 'end' => '18:00'],
        1 => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
        2 => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
        3 => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
        4 => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
        5 => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
        6 => ['enabled' => false, 'start' => '09:00', 'end' => '18:00'],
    ],

    'closed_dates' => [],
];
