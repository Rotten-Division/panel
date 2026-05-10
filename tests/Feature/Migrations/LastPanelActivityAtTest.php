<?php

use Illuminate\Support\Facades\Schema;

test('servers.last_panel_activity_at column exists and is nullable', function () {
    $columns = collect(Schema::getColumns('servers'));
    $column = $columns->firstWhere('name', 'last_panel_activity_at');

    expect($column)->not->toBeNull();
    expect($column['nullable'])->toBeTrue();
});

test('compound index on status, last_panel_activity_at exists', function () {
    $indexes = collect(Schema::getIndexes('servers'));
    $hit = $indexes->first(function (array $index) {
        $cols = $index['columns'];

        return in_array('status', $cols, true) && in_array('last_panel_activity_at', $cols, true);
    });

    expect($hit)->not->toBeNull();
});
