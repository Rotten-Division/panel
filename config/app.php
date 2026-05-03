<?php

return [

    'name' => env('APP_NAME', 'Pelican'),
    'logo' => env('APP_LOGO'),
    'favicon' => env('APP_FAVICON', '/pelican.ico'),

    // local checkouts default to canary which routes through the canary
    // plugin update channel, prod images set APP_VERSION via the docker
    // workflow to canary short sha on main builds or v tag on releases.
    // the ?: coalesce treats an empty env value as missing so a blank
    // APP_VERSION line in env never lands the panel on the release
    // channel with an empty version string.
    'version' => env('APP_VERSION') ?: 'canary',

    'timezone' => 'UTC',

    'installed' => env('APP_INSTALLED', true),

    'exceptions' => [
        'report_all' => env('APP_REPORT_ALL_EXCEPTIONS', false),
    ],

    'fallback_locale' => 'en',

];
