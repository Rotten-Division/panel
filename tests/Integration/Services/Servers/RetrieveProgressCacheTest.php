<?php

use App\Enums\ServerState;
use App\Services\Servers\RetrieveProgressCache;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('seed then merge unions panel + wings fields', function () {
    $server = $this->createServerModel();
    $cache = new RetrieveProgressCache();

    $cache->seed($server, ['requested_by' => 'a@b.c', 'node_name' => 'wings-eu-2']);
    $cache->mergeProgress($server, ['step' => 'downloading', 'bytes' => 5, 'total_bytes' => 10]);

    $got = $cache->get($server);
    expect($got['requested_by'])->toBe('a@b.c')
        ->and($got['node_name'])->toBe('wings-eu-2')
        ->and($got['step'])->toBe('downloading')
        ->and($got['bytes'])->toBe(5)
        ->and($got['streaming_started_at'])->toBeInt();
});

test('streaming_started_at is stamped once and not bumped on later ticks', function () {
    $server = $this->createServerModel();
    $cache = new RetrieveProgressCache();

    $cache->mergeProgress($server, ['step' => 'downloading', 'bytes' => 1, 'total_bytes' => 10]);
    $first = $cache->get($server)['streaming_started_at'];
    $cache->mergeProgress($server, ['step' => 'downloading', 'bytes' => 6, 'total_bytes' => 10]);

    expect($cache->get($server)['streaming_started_at'])->toBe($first);
});

test('forget clears the entry', function () {
    $server = $this->createServerModel();
    $cache = new RetrieveProgressCache();
    $cache->seed($server, ['requested_by' => 'x']);
    $cache->forget($server);
    expect($cache->get($server))->toBeNull();
});

test('entries are scoped per server uuid', function () {
    $a = $this->createServerModel();
    $b = $this->createServerModel();
    $cache = new RetrieveProgressCache();

    $cache->seed($a, ['requested_by' => 'user-a']);
    $cache->seed($b, ['requested_by' => 'user-b']);

    expect($cache->get($a)['requested_by'])->toBe('user-a');
    expect($cache->get($b)['requested_by'])->toBe('user-b');
});

test('seed does not clobber existing wings fields on second call', function () {
    $server = $this->createServerModel();
    $cache = new RetrieveProgressCache();

    $cache->mergeProgress($server, ['step' => 'downloading', 'bytes' => 50, 'total_bytes' => 100]);
    $cache->seed($server, ['requested_by' => 'late@seed.com']);

    $got = $cache->get($server);
    expect($got['step'])->toBe('downloading')
        ->and($got['bytes'])->toBe(50)
        ->and($got['requested_by'])->toBe('late@seed.com');
});

test('starting step keeps streamed totals and stamps finished', function () {
    $server = $this->createServerModel(['status' => ServerState::Retrieving]);
    $cache = app(RetrieveProgressCache::class);

    $cache->mergeProgress($server, ['step' => 'downloading', 'bytes' => 500, 'total_bytes' => 1000]);
    $cache->mergeProgress($server, ['step' => 'starting', 'bytes' => 0, 'total_bytes' => 0]);

    $p = $cache->get($server);
    $this->assertSame('starting', $p['step']);
    $this->assertSame(1000, $p['total_bytes']);
    $this->assertIsInt($p['streaming_finished_at']);
});
