<?php

use App\Enums\ServerState;

// Server::status casts to ServerState, so these values are written to the
// database. renaming a case value would orphan every stored row, so pin them.

test('stashed persists as the "stashed" string', function () {
    expect(ServerState::Stashed->value)->toBe('stashed');
});

test('retrieving persists as the "retrieving" string', function () {
    expect(ServerState::Retrieving->value)->toBe('retrieving');
});

test('stashing persists as the "stashing" string', function () {
    expect(ServerState::Stashing->value)->toBe('stashing');
});
