<?php

use App\Enums\ServerState;
use App\Enums\TablerIcon;

test('stashed case exists', function () {
    expect(ServerState::Stashed->value)->toBe('stashed');
});

test('retrieving case exists', function () {
    expect(ServerState::Retrieving->value)->toBe('retrieving');
});

test('stashing case exists for the in-flight capture window', function () {
    expect(ServerState::Stashing->value)->toBe('stashing');
});

test('stashing has package-export icon and primary colour', function () {
    expect(ServerState::Stashing->getIcon())->toBe(TablerIcon::PackageExport);
    expect(ServerState::Stashing->getColor())->toBe('primary');
});

test('stashed has snowflake icon', function () {
    expect(ServerState::Stashed->getIcon())->toBe(TablerIcon::Snowflake);
});

test('retrieving has heart-bolt icon', function () {
    expect(ServerState::Retrieving->getIcon())->toBe(TablerIcon::HeartBolt);
});

test('stashed is info colour', function () {
    expect(ServerState::Stashed->getColor())->toBe('info');
});

test('retrieving is primary colour', function () {
    expect(ServerState::Retrieving->getColor())->toBe('primary');
});

test('stashed label is humanised', function () {
    expect(ServerState::Stashed->getLabel())->toBe('Stashed');
});

test('retrieving label is humanised', function () {
    expect(ServerState::Retrieving->getLabel())->toBe('Retrieving');
});
