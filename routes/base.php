<?php

use App\Livewire\Installer\PanelInstaller;
use Illuminate\Support\Facades\Route;

Route::get('installer', PanelInstaller::class)->name('installer')
    ->withoutMiddleware(['auth']);

// the per-server Console page was renamed to Overview in the server view
// redesign. bookmarks and emailed links keep working via a 301.
Route::redirect('/server/{tenant}/console', '/server/{tenant}/overview', 301);
