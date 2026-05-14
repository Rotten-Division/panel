<?php

// pin every database/cache/session/queue env to a safe in-process value
// BEFORE Laravel boots. phpunit.xml <env force="true"> directives don't
// reliably override docker-injected env vars (e.g. DB_CONNECTION=pgsql baked
// into the container image), and DatabaseTruncation hitting prod postgres
// truncates the panel's real data — the test factory once wrote a fake
// `shea82_ugksxeuzxg` user and pair of `Faustino`/`Dorcas` servers into
// production. this file is the single hard barrier between the test suite
// and the production database. don't relax it.
$forced = [
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_HOST' => '',
    'DB_PORT' => '',
    'DB_USERNAME' => '',
    'DB_PASSWORD' => '',
    'GUZZLE_CONNECT_TIMEOUT' => '1',
    'MAIL_MAILER' => 'array',
    'PULSE_ENABLED' => 'false',
    'QUEUE_CONNECTION' => 'sync',
    'REDIS_HOST' => '',
    'SESSION_DRIVER' => 'array',
    'TELESCOPE_ENABLED' => 'false',
];

foreach ($forced as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
