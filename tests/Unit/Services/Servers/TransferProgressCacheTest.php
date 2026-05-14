<?php

use App\Services\Servers\TransferProgressCache;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('put then get round trips the payload', function () {
    $server = $this->createServerModel();
    $cache = new TransferProgressCache();

    $cache->put($server, [
        'step' => 'uploading',
        'bytes' => 50_000_000,
        'total_bytes' => 100_000_000,
    ]);

    expect($cache->get($server))->toBe([
        'step' => 'uploading',
        'bytes' => 50_000_000,
        'total_bytes' => 100_000_000,
    ]);
});

test('get returns null when no entry is cached', function () {
    $server = $this->createServerModel();

    expect((new TransferProgressCache())->get($server))->toBeNull();
});

test('forget removes the cached entry', function () {
    $server = $this->createServerModel();
    $cache = new TransferProgressCache();
    $cache->put($server, ['step' => 'uploading', 'bytes' => 1, 'total_bytes' => 2]);
    $cache->forget($server);

    expect($cache->get($server))->toBeNull();
});

test('entries are scoped per server uuid', function () {
    $a = $this->createServerModel();
    $b = $this->createServerModel();
    $cache = new TransferProgressCache();

    $cache->put($a, ['step' => 'uploading', 'bytes' => 1, 'total_bytes' => 10]);
    $cache->put($b, ['step' => 'extracting', 'bytes' => 5, 'total_bytes' => 0]);

    expect($cache->get($a)['step'])->toBe('uploading');
    expect($cache->get($b)['step'])->toBe('extracting');
});
