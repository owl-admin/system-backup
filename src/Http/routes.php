<?php

use Slowlyo\OwlSystemBackup\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('owl-menu-backup', [Controllers\OwlSystemBackupController::class, 'index']);
Route::post('owl-menu-backup', [Controllers\OwlSystemBackupController::class, 'addBackup']);
Route::put('owl-menu-backup', [Controllers\OwlSystemBackupController::class, 'recover']);
Route::delete('owl-menu-backup', [Controllers\OwlSystemBackupController::class, 'remove']);
