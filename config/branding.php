<?php

/*
|--------------------------------------------------------------------------
| Product Branding
|--------------------------------------------------------------------------
|
| Product name shown in the UI (landing page, sidebar, auth screens, titles).
| Independent of APP_NAME so branding is consistent regardless of the server
| environment. Override with the BRAND_NAME env var if desired.
|
*/

return [
    'name' => env('BRAND_NAME', 'Sales Management Service'),
    'short' => env('BRAND_SHORT', 'SMS'),
    'tagline' => env('BRAND_TAGLINE', 'Commissions your reps actually trust'),
];
