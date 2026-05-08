<?php

declare(strict_types=1);

use Filament\Support\Icons\Heroicon;

return [

    /* -----------------------------------------------------------------
    | Driver
    | -----------------------------------------------------------------
    | Available drivers: 'daily', 'stack', 'raw'
    | -----------------------------------------------------------------
     */

    'driver' => env('FILAMENT_LOG_VIEWER_DRIVER', env('LOG_CHANNEL', 'stack')),

    /* -----------------------------------------------------------------
    | Resource configuration
    | -----------------------------------------------------------------
     */

    'resource' => [
        'slug' => 'logs',
        'cluster' => null,
    ],

    /* -----------------------------------------------------------------
    | Logs files can be cleared
    | -----------------------------------------------------------------
    */

    'clearable' => env('FILAMENT_LOG_VIEWER_CLEARABLE', false),

    /* -----------------------------------------------------------------
    |  Log files storage path
    | -----------------------------------------------------------------
     */

    'storage_path' => storage_path('logs'),

    /* -----------------------------------------------------------------
    |  Log files pattern
    | -----------------------------------------------------------------
     */

    'pattern' => [
        'prefix' => 'laravel-',
        'date' => '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]',
        'extension' => '.log',
    ],

    /* -----------------------------------------------------------------
    |  Log entries per page
    | -----------------------------------------------------------------
    |  This defines how many logs and entries are displayed per page.
     */

    'per-page' => [
        5,
        10,
        25,
        30,
    ],

    /* -----------------------------------------------------------------
    |  Download settings
    | -----------------------------------------------------------------
     */

    'download' => [
        'prefix' => 'laravel-',

        'extension' => 'log',
    ],

    /* -----------------------------------------------------------------
    |  Icons
    | -----------------------------------------------------------------
     */

    'icons' => [
        'all' => Heroicon::ListBullet,
        'emergency' => Heroicon::BugAnt,
        'alert' => Heroicon::Megaphone,
        'critical' => Heroicon::Fire,
        'error' => Heroicon::XCircle,
        'warning' => Heroicon::ExclamationTriangle,
        'notice' => Heroicon::ExclamationCircle,
        'info' => Heroicon::InformationCircle,
        'debug' => Heroicon::CommandLine,
    ],

    /* -----------------------------------------------------------------
    |  Colors
    | -----------------------------------------------------------------
     */

    'colors' => [
        'levels' => [
            'all' => '#5C6878',
            'emergency' => '#8C2D2D',
            'alert' => '#B43C3C',
            'critical' => '#B43C3C',
            'error' => '#B43C3C',
            'warning' => '#D89020',
            'notice' => '#5C6878',
            'info' => '#5C6878',
            'debug' => '#5C6878',
        ],
    ],

    /* -----------------------------------------------------------------
    |  Strings to highlight in stack trace
    | -----------------------------------------------------------------
     */

    'highlight' => [
        '^#\d+', '^Stack trace:',
    ],
];
