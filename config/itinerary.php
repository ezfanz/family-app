<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stop photos
    |--------------------------------------------------------------------------
    |
    | Photos are looked up on Wikipedia / Wikimedia Commons (free, openly
    | licensed, credited). Uploaded photos go to the same private disk as
    | receipt photos and are streamed only to trip members.
    |
    */

    /*
    | Look up a photo automatically when a stop is added or renamed (off in tests).
    */
    'auto_photos' => (bool) env('STOP_PHOTO_LOOKUP', true),

    'photo_disk' => env('PHOTOS_DISK', env('RECEIPTS_DISK', 'receipts')),

    'wikipedia_api' => env('WIKIPEDIA_API_URL', 'https://en.wikipedia.org/w/api.php'),

    'commons_api' => env('COMMONS_API_URL', 'https://commons.wikimedia.org/w/api.php'),

    /*
    | Wikimedia asks API clients to identify themselves.
    */
    'user_agent' => 'E-Itinerary/1.0 (Laravel trip planner)',

    /*
    |--------------------------------------------------------------------------
    | Trip members added by the TripMembersSeeder
    |--------------------------------------------------------------------------
    |
    | "Name:email" pairs, comma separated, and their first password. Kept in
    | .env (never in git); each person can change the password afterwards.
    |
    */

    'seed_members' => env('TRIP_MEMBERS', ''),

    'seed_member_password' => env('TRIP_MEMBER_PASSWORD'),

];
