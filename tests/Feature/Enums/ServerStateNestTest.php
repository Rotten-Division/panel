<?php

use App\Enums\ServerState;
use App\Enums\TablerIcon;

test('nest case exists', function () {
    expect(ServerState::Nest->value)->toBe('nest');
});

test('hydrating case exists', function () {
    expect(ServerState::Hydrating->value)->toBe('hydrating');
});

test('capturing case exists for the eviction in flight window', function () {
    expect(ServerState::Capturing->value)->toBe('capturing');
});

test('capturing has package-export icon and primary colour', function () {
    expect(ServerState::Capturing->getIcon())->toBe(TablerIcon::PackageExport);
    expect(ServerState::Capturing->getColor())->toBe('primary');
});

test('nest has snowflake icon', function () {
    expect(ServerState::Nest->getIcon())->toBe(TablerIcon::Snowflake);
});

test('hydrating has heart-bolt icon', function () {
    expect(ServerState::Hydrating->getIcon())->toBe(TablerIcon::HeartBolt);
});

test('nest is info colour', function () {
    expect(ServerState::Nest->getColor())->toBe('info');
});

test('hydrating is primary colour', function () {
    expect(ServerState::Hydrating->getColor())->toBe('primary');
});

test('nest label is humanised', function () {
    expect(ServerState::Nest->getLabel())->toBe('Nest');
});

test('hydrating label is humanised', function () {
    expect(ServerState::Hydrating->getLabel())->toBe('Hydrating');
});
