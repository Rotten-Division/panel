<?php

use App\Filament\Server\Widgets\ServerConsole;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('storeStats preserves the unix timestamp keys when slicing the cache', function () {
    // pre-fix this method silently reindexed integer keys to 0..N because
    // array_slice's default behaviour reindexes integer keys when
    // preserve_keys is false. that broke every downstream consumer that
    // walked array_keys() to recover sample timestamps.
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $server = Server::factory()->for($user, 'user')->create();

    $widget = new ServerConsole();
    $widget->server = $server;

    $now = now()->getTimestamp();
    $widget->storeStats(json_encode(['cpu_absolute' => 12.5]));

    $cached = cache()->get("servers.{$server->id}.cpu_absolute");
    $keys = array_keys($cached);

    // the single key must be the unix timestamp we wrote, not 0
    expect($keys)->toHaveCount(1);
    expect($keys[0])->toBeGreaterThanOrEqual($now);
    expect($keys[0])->not->toBe(0);
});

test('storeStats preserves keys across many writes up to the 120 sample cap', function () {
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $server = Server::factory()->for($user, 'user')->create();

    $widget = new ServerConsole();
    $widget->server = $server;

    // prime the cache with 121 timestamp-keyed entries directly, then
    // trigger one more write so the slice path runs against a full cache.
    $entries = [];
    for ($i = 0; $i < 121; $i++) {
        $entries[1_700_000_000 + $i] = (float) $i;
    }
    cache()->put("servers.{$server->id}.cpu_absolute", $entries, now()->addMinute());

    $widget->storeStats(json_encode(['cpu_absolute' => 99.9]));

    $cached = cache()->get("servers.{$server->id}.cpu_absolute");

    expect($cached)->toHaveCount(120);
    // every key must remain a real unix timestamp
    foreach (array_keys($cached) as $key) {
        expect($key)->toBeGreaterThan(1_000_000_000);
    }
});
