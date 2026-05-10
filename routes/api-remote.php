<?php

use App\Http\Controllers\Api\Remote;
use Illuminate\Support\Facades\Route;

// Routes for the daemon.
Route::post('/sftp/auth', Remote\SftpAuthenticationController::class);

Route::get('/servers', [Remote\Servers\ServerDetailsController::class, 'list']);
Route::post('/servers/reset', [Remote\Servers\ServerDetailsController::class, 'resetState']);
Route::post('/activity', Remote\ActivityProcessingController::class);

Route::prefix('/servers/{server:uuid}')->group(function () {
    Route::get('/', Remote\Servers\ServerDetailsController::class);
    Route::get('/install', [Remote\Servers\ServerInstallController::class, 'index']);
    Route::post('/install', [Remote\Servers\ServerInstallController::class, 'store']);

    Route::post('/transfer/failure', [Remote\Servers\ServerTransferController::class, 'failure']);
    Route::post('/transfer/success', [Remote\Servers\ServerTransferController::class, 'success']);

    Route::post('/container/status', [Remote\Servers\ServerContainersController::class, 'status']);

    Route::post('/nest/captured', [Remote\Servers\NestRemoteController::class, 'captured'])
        ->name('api.remote.servers.nest.captured');
    Route::post('/nest/restored', [Remote\Servers\NestRemoteController::class, 'restored'])
        ->name('api.remote.servers.nest.restored');
});

Route::prefix('/backups')->group(function () {
    Route::get('/{backup:uuid}', Remote\Backups\BackupRemoteUploadController::class);
    Route::post('/{backup:uuid}', [Remote\Backups\BackupStatusController::class, 'index']);
    Route::post('/{backup:uuid}/restore', [Remote\Backups\BackupStatusController::class, 'restore']);
});
