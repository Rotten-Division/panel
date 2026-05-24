<?php

use App\Enums\ContainerStatus;
use App\Enums\ServerState;
use App\View\Components\Overview\StatePill;

test('variant maps each ServerState to the expected pill style', function () {
    expect((new StatePill(ServerState::Stashed))->variant())->toBe('stashed');
    expect((new StatePill(ServerState::Retrieving))->variant())->toBe('stashed');
    expect((new StatePill(ServerState::Stashing))->variant())->toBe('stashed');
    expect((new StatePill(ServerState::Installing))->variant())->toBe('installing');
    expect((new StatePill(ServerState::RestoringBackup))->variant())->toBe('installing');
    expect((new StatePill(ServerState::InstallFailed))->variant())->toBe('suspended');
    expect((new StatePill(ServerState::ReinstallFailed))->variant())->toBe('suspended');
    expect((new StatePill(ServerState::Suspended))->variant())->toBe('suspended');
});

test('transferring flag overrides any state', function () {
    $pill = new StatePill(ServerState::Stashed, ContainerStatus::Running, transferring: true);

    expect($pill->variant())->toBe('transient');
    expect($pill->label())->toBe('TRANSFERRING');
});

test('null state disambiguates via container status', function () {
    expect((new StatePill(null, ContainerStatus::Running))->variant())->toBe('online');
    expect((new StatePill(null, ContainerStatus::Starting))->variant())->toBe('transient');
    expect((new StatePill(null, ContainerStatus::Stopping))->variant())->toBe('transient');
    expect((new StatePill(null, ContainerStatus::Restarting))->variant())->toBe('transient');
    expect((new StatePill(null, ContainerStatus::Offline))->variant())->toBe('stopped');
    expect((new StatePill(null, ContainerStatus::Exited))->variant())->toBe('stopped');
});

test('label upper-cases the state value and replaces underscores', function () {
    expect((new StatePill(ServerState::InstallFailed))->label())->toBe('INSTALL FAILED');
    expect((new StatePill(ServerState::RestoringBackup))->label())->toBe('RESTORING BACKUP');
    expect((new StatePill(ServerState::Stashed))->label())->toBe('STASHED');
});

test('pulses returns true only for transient and installing variants', function () {
    expect((new StatePill(null, ContainerStatus::Starting))->pulses())->toBeTrue();
    expect((new StatePill(ServerState::Installing))->pulses())->toBeTrue();
    expect((new StatePill(ServerState::Stashed))->pulses())->toBeFalse();
    expect((new StatePill(null, ContainerStatus::Running))->pulses())->toBeFalse();
});
