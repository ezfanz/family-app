<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Japan tax refund (refund method from 1 Nov 2026)
    |--------------------------------------------------------------------------
    |
    | Purchases at one store on one day (Japan time) qualify when their
    | before-tax total reaches the minimum. Goods must leave Japan within
    | "days to export" of purchase. Both rules are still being verified,
    | so they live here rather than in code.
    |
    */

    'min_pre_tax_yen' => (int) env('TAX_REFUND_MIN_PRE_TAX_YEN', 5000),

    'days_to_export' => (int) env('TAX_REFUND_DAYS_TO_EXPORT', 90),

    'timezone' => 'Asia/Tokyo',

    /*
    | Consumption tax rates in percent: standard and reduced (food and drink).
    */
    'rates' => [10, 8],

    /*
    | Private disk for receipt photos. "receipts" is a private local folder;
    | set RECEIPTS_DISK=s3 with a private Supabase Storage bucket in production.
    */
    'disk' => env('RECEIPTS_DISK', 'receipts'),

];
