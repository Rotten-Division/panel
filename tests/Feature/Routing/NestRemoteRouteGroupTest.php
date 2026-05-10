<?php

use Illuminate\Support\Facades\Route;

test('nest captured route is registered under api/remote', function () {
    $route = collect(Route::getRoutes())
        ->first(fn ($r) => $r->uri() === 'api/remote/servers/{server}/nest/captured');

    expect($route)->not->toBeNull();
    expect($route->methods())->toContain('POST');
});

test('nest restored route is registered under api/remote', function () {
    $route = collect(Route::getRoutes())
        ->first(fn ($r) => $r->uri() === 'api/remote/servers/{server}/nest/restored');

    expect($route)->not->toBeNull();
    expect($route->methods())->toContain('POST');
});

test('nest routes use the daemon middleware group', function () {
    $route = collect(Route::getRoutes())
        ->first(fn ($r) => $r->uri() === 'api/remote/servers/{server}/nest/captured');

    expect($route->middleware())->toContain('daemon');
});
