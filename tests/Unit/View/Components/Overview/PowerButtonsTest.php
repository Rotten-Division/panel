<?php

use App\Enums\ContainerStatus;
use App\Enums\ServerState;
use App\Models\Server;
use App\Models\ServerTransfer;
use App\View\Components\Overview\PowerButtons;

function buildServerStub(?ServerState $status = null, ?ServerTransfer $transfer = null): Server
{
    $server = new Server();
    $server->status = $status;
    $server->setRelation('transfer', $transfer);

    return $server;
}

test('shouldHide returns true when a transfer is in flight', function () {
    $transfer = new ServerTransfer();
    $transfer->successful = null;
    $server = buildServerStub(null, $transfer);

    expect((new PowerButtons($server))->shouldHide())->toBeTrue();
});

test('shouldHide returns false when a transfer has completed', function () {
    $transfer = new ServerTransfer();
    $transfer->successful = true;
    $server = buildServerStub(null, $transfer);

    expect((new PowerButtons($server))->shouldHide())->toBeFalse();
});

test('shouldHide returns true for nest, hydrating, capturing', function () {
    expect((new PowerButtons(buildServerStub(ServerState::Nest)))->shouldHide())->toBeTrue();
    expect((new PowerButtons(buildServerStub(ServerState::Hydrating)))->shouldHide())->toBeTrue();
    expect((new PowerButtons(buildServerStub(ServerState::Capturing)))->shouldHide())->toBeTrue();
});

test('shouldHide returns true for installing, suspended, install_failed', function () {
    expect((new PowerButtons(buildServerStub(ServerState::Installing)))->shouldHide())->toBeTrue();
    expect((new PowerButtons(buildServerStub(ServerState::Suspended)))->shouldHide())->toBeTrue();
    expect((new PowerButtons(buildServerStub(ServerState::InstallFailed)))->shouldHide())->toBeTrue();
    expect((new PowerButtons(buildServerStub(ServerState::ReinstallFailed)))->shouldHide())->toBeTrue();
    expect((new PowerButtons(buildServerStub(ServerState::RestoringBackup)))->shouldHide())->toBeTrue();
});

test('shouldHide returns false for null status (operational)', function () {
    expect((new PowerButtons(buildServerStub(null)))->shouldHide())->toBeFalse();
});

test('canStart enabled only when container is Offline', function () {
    $server = buildServerStub(null);

    expect((new PowerButtons($server, ContainerStatus::Offline))->canStart())->toBeTrue();
    expect((new PowerButtons($server, ContainerStatus::Running))->canStart())->toBeFalse();
    expect((new PowerButtons($server, ContainerStatus::Starting))->canStart())->toBeFalse();
    expect((new PowerButtons($server, null))->canStart())->toBeFalse();
});

test('canRestart enabled only when container is Running', function () {
    $server = buildServerStub(null);

    expect((new PowerButtons($server, ContainerStatus::Running))->canRestart())->toBeTrue();
    expect((new PowerButtons($server, ContainerStatus::Offline))->canRestart())->toBeFalse();
    expect((new PowerButtons($server, ContainerStatus::Starting))->canRestart())->toBeFalse();
});

test('canStop enabled when Running or Starting', function () {
    $server = buildServerStub(null);

    expect((new PowerButtons($server, ContainerStatus::Running))->canStop())->toBeTrue();
    expect((new PowerButtons($server, ContainerStatus::Starting))->canStop())->toBeTrue();
    expect((new PowerButtons($server, ContainerStatus::Offline))->canStop())->toBeFalse();
    expect((new PowerButtons($server, ContainerStatus::Stopping))->canStop())->toBeFalse();
});
