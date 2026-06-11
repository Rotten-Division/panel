<?php

use App\Models\Node;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

covers(Node::class);

it('sends the panel name to wings as app_name', function () {
    $node = Node::factory()->create();

    expect($node->getConfiguration()['app_name'])->toBe(config('app.name'));
});

it('tracks the panel name when it changes', function () {
    $node = Node::factory()->create();
    config(['app.name' => 'Renamed Panel']);

    expect($node->getConfiguration()['app_name'])->toBe('Renamed Panel');
});
