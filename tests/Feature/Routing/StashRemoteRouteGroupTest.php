<?php

use Illuminate\Support\Facades\Route;

// the URL path retains the /nest segment so wings does not need to learn
// a new wire format and existing signed warning-email URLs stay live.

test('stash captured route is registered under api/remote', function () {
    $route = collect(Route::getRoutes())
        ->first(fn ($r) => $r->uri() === 'api/remote/servers/{server}/nest/captured');

    expect($route)->not->toBeNull();
    expect($route->methods())->toContain('POST');
});

test('stash restored route is registered under api/remote', function () {
    $route = collect(Route::getRoutes())
        ->first(fn ($r) => $r->uri() === 'api/remote/servers/{server}/nest/restored');

    expect($route)->not->toBeNull();
    expect($route->methods())->toContain('POST');
});

test('stash routes use the daemon middleware group', function () {
    $route = collect(Route::getRoutes())
        ->first(fn ($r) => $r->uri() === 'api/remote/servers/{server}/nest/captured');

    expect($route->middleware())->toContain('daemon');
});
