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

    /*
    | Automatic receipt reading. "claude" sends the scan to the Claude API
    | (needs ANTHROPIC_API_KEY); "manual" reads nothing and the person types.
    | The quick model reads first; the careful model takes a second look only
    | when the quick one is unsure about the store, date or amount.
    */
    'reader' => env('RECEIPT_READER', 'claude'),

    'reader_models' => [
        'quick' => env('RECEIPT_READER_QUICK_MODEL', 'claude-haiku-5-5'),
        'careful' => env('RECEIPT_READER_CAREFUL_MODEL', 'claude-sonnet-5-5'),
    ],

    /*
    | Below this confidence (0 to 1) a field is flagged "Check this" and,
    | for the store, date or amount, the careful model reads it again.
    */
    'reader_min_confidence' => (float) env('RECEIPT_READER_MIN_CONFIDENCE', 0.75),

];
