<?php

use App\Models\Egg;

test('Egg::game returns the value after the game: prefix', function () {
    $egg = new Egg();
    $egg->tags = ['game:minecraft', 'version_var:MC_VERSION'];

    expect($egg->game)->toBe('minecraft');
});

test('Egg::game returns null when no game tag is set', function () {
    $egg = new Egg();
    $egg->tags = ['version_var:MC_VERSION'];

    expect($egg->game)->toBeNull();
});

test('Egg::versionVar returns the value after the version_var: prefix', function () {
    $egg = new Egg();
    $egg->tags = ['game:minecraft', 'version_var:MC_VERSION'];

    expect($egg->versionVar)->toBe('MC_VERSION');
});

test('Egg::versionVar returns null when no version_var tag is set', function () {
    $egg = new Egg();
    $egg->tags = ['game:minecraft'];

    expect($egg->versionVar)->toBeNull();
});

test('Egg::game tolerates unknown namespaces', function () {
    $egg = new Egg();
    $egg->tags = ['game:minecraft', 'variant:forge', 'loader:35.1.0'];

    expect($egg->game)->toBe('minecraft');
    expect($egg->versionVar)->toBeNull();
});
