<?php

use App\Models\Server;
use App\View\Components\Overview\TransferDetail;

test('stepLabel maps known steps to friendly copy', function () {
    $component = new TransferDetail(new Server());

    expect($component->stepLabel('archiving'))->toBe('Archiving server files');
    expect($component->stepLabel('uploading'))->toBe('Uploading to destination');
    expect($component->stepLabel('extracting'))->toBe('Unpacking on destination');
    expect($component->stepLabel('verifying'))->toBe('Verifying integrity');
    expect($component->stepLabel('cleanup'))->toBe('Cleaning up temporary files');
});

test('stepLabel falls back to ucfirst for unknown steps', function () {
    $component = new TransferDetail(new Server());

    expect($component->stepLabel('frobnicating'))->toBe('Frobnicating');
});

test('bytesLabel scales bytes to readable units', function () {
    $component = new TransferDetail(new Server());

    expect($component->bytesLabel(0))->toBe('0 B');
    expect($component->bytesLabel(512))->toBe('512 B');
    expect($component->bytesLabel(2048))->toBe('2.0 KiB');
    expect($component->bytesLabel(5 * 1024 * 1024))->toBe('5.0 MiB');
    expect($component->bytesLabel(3 * 1024 * 1024 * 1024))->toBe('3.0 GiB');
});
