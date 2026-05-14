<?php

use App\Models\Egg;
use App\Models\EggVariable;
use App\Models\Server;

test('Server::game proxies to egg game tag', function () {
    $egg = new Egg();
    $egg->tags = ['game:minecraft', 'version_var:MC_VERSION'];
    $server = new Server();
    $server->setRelation('egg', $egg);

    expect($server->game)->toBe('minecraft');
});

test('Server::flavour is the egg name', function () {
    $egg = new Egg();
    $egg->name = 'Forge';
    $server = new Server();
    $server->setRelation('egg', $egg);

    expect($server->flavour)->toBe('Forge');
});

test('Server::version reads the egg-declared version variable from variables relation', function () {
    $egg = new Egg();
    $egg->tags = ['game:minecraft', 'version_var:MC_VERSION'];

    $variable = new EggVariable();
    $variable->env_variable = 'MC_VERSION';
    $variable->server_value = '1.21.11';

    $server = new Server();
    $server->setRelation('egg', $egg);
    $server->setRelation('variables', collect([$variable]));

    expect($server->version)->toBe('1.21.11');
});

test('Server::version returns null when version_var tag is missing', function () {
    $egg = new Egg();
    $egg->tags = ['game:minecraft'];
    $server = new Server();
    $server->setRelation('egg', $egg);
    $server->setRelation('variables', collect());

    expect($server->version)->toBeNull();
});

test('Server::version returns null when the named variable has no value', function () {
    $egg = new Egg();
    $egg->tags = ['game:minecraft', 'version_var:MC_VERSION'];
    $server = new Server();
    $server->setRelation('egg', $egg);
    $server->setRelation('variables', collect());

    expect($server->version)->toBeNull();
});
