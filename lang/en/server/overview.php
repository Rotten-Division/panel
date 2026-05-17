<?php

return [
    'title' => 'Overview',
    'nav' => [
        'label' => 'Overview',
    ],

    /*
    | banner copy for transient state partials. apostrophes avoided ("game
    | is" rather than "game's") so the strings stay safe in single-quoted
    | php and don't need escaping when copied into other locales.
    */
    'transient' => [
        'starting' => [
            'title' => 'Starting up.',
            'subtitle' => 'The container is booting and the game is coming online. The console below is live; charts will populate once metrics start flowing.',
            'uptime' => 'Starting',
        ],
        'stopping' => [
            'title' => 'Stopping.',
            'subtitle' => 'Saving the game and shutting down. The container will drop offline once cleanup finishes.',
            'uptime' => 'Stopping',
        ],
        'restarting' => [
            'title' => 'Restarting.',
            'subtitle' => 'Wings is cycling the container. The world is safe; this takes about a minute.',
            'uptime' => 'Restarting',
        ],
        'restoring_backup' => [
            'title' => 'Restoring backup.',
            'subtitle' => 'Streaming backup contents onto the server. We will drop you into the live view as soon as it is back.',
            'uptime' => 'Restoring',
        ],
    ],

    'stopped' => [
        // canvas verbatim — "while it's down" with the contraction (matches
        // the apostrophe usage in the title for tonal consistency).
        'title' => "This server's stopped.",
        'subtitle' => "Hit start in the header to bring it back. Files, backups and schedules stay available while it's down.",
    ],

    'installing' => [
        'installing' => [
            'title' => 'Setting up your server.',
            'subtitle' => 'The install script is running on a fresh container. The console is read-only until this finishes; the server will start automatically.',
        ],
        'install_failed' => [
            'title' => 'Install failed.',
            'subtitle' => 'Setup did not complete. Reinstall the server from Settings.',
        ],
        'reinstall_failed' => [
            'title' => 'Reinstall failed.',
            'subtitle' => 'The reinstall did not finish. Try again from Settings.',
        ],
    ],
];
