<?php

return [
    'service_fee_rate' => (float) env('WARRANTY_SERVICE_FEE_RATE', 0.025),
    'quote_valid_days' => (int) env('WARRANTY_QUOTE_VALID_DAYS', 15),
    'price_min' => (float) env('WARRANTY_PRICE_MIN', 50000),
    'price_max' => (float) env('WARRANTY_PRICE_MAX', 1500000),
];
