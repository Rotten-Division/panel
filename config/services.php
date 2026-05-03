<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'github' => [
        // private access token used for fetching plugin update manifests and
        // release asset zips from the ospite plugin repo. read only contents
        // and releases scopes are sufficient.
        'plugin_token' => env('GITHUB_PLUGIN_TOKEN'),
        'plugin_user_agent' => env('GITHUB_PLUGIN_USER_AGENT', 'OspiteHosting-Panel'),
    ],

];
