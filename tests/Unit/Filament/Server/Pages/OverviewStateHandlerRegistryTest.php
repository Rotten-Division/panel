<?php

use App\Contracts\Servers\OverviewStateHandler;
use App\Filament\Server\Pages\Overview;
use App\Models\Server;
use Illuminate\Contracts\View\View;

// the registry is a static array on Overview, so each test snapshots
// and restores it. otherwise a registration from one test bleeds into
// the next, which is the exact subtle failure mode we are guarding
// against (handlers persisting across requests when they should not).
beforeEach(function () {
    $ref = new ReflectionClass(Overview::class);
    $prop = $ref->getProperty('stateHandlers');
    $this->snapshot = $prop->getValue();
    $prop->setValue(null, []);
});

afterEach(function () {
    $ref = new ReflectionClass(Overview::class);
    $prop = $ref->getProperty('stateHandlers');
    $prop->setValue(null, $this->snapshot);
});

class AcceptingStateHandler implements OverviewStateHandler
{
    public function handles(Server $server): bool
    {
        return true;
    }

    public function render(Server $server): View
    {
        return view('welcome');
    }

    public function actions(Server $server): array
    {
        return [];
    }
}

class RejectingStateHandler implements OverviewStateHandler
{
    public function handles(Server $server): bool
    {
        return false;
    }

    public function render(Server $server): View
    {
        return view('welcome');
    }

    public function actions(Server $server): array
    {
        return [];
    }
}

test('resolveStateHandler returns null when no handler is registered', function () {
    $page = new Overview();
    expect($page->resolveStateHandler(new Server()))->toBeNull();
});

test('resolveStateHandler returns the first handler whose handles returns true', function () {
    Overview::registerStateHandler(RejectingStateHandler::class);
    Overview::registerStateHandler(AcceptingStateHandler::class);

    $page = new Overview();
    // resolveStateHandler resolves handlers through the container boot() injects;
    // bypassing the filament lifecycle here means setting it ourselves.
    (fn () => $this->container = app())->call($page);
    $resolved = $page->resolveStateHandler(new Server());

    expect($resolved)->toBeInstanceOf(AcceptingStateHandler::class);
});

test('registerStateHandler preserves order so the first match wins', function () {
    Overview::registerStateHandler(AcceptingStateHandler::class);
    // a second accepter registered later must not displace the first
    Overview::registerStateHandler(AcceptingStateHandler::class);

    $ref = new ReflectionClass(Overview::class);
    $prop = $ref->getProperty('stateHandlers');
    expect($prop->getValue())->toHaveCount(2);
});
