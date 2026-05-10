<?php

use App\Models\Server;
use Illuminate\Support\Facades\Schema;

test('servers.node_id column is nullable', function () {
    $columns = collect(Schema::getColumns('servers'));
    $column = $columns->firstWhere('name', 'node_id');

    expect($column)->not->toBeNull();
    expect($column['nullable'])->toBeTrue();
});

test('Server validation rule for node_id allows null', function () {
    $rules = Server::$validationRules['node_id'];
    expect($rules)->toContain('nullable');
});
