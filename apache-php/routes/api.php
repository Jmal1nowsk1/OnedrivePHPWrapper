<?php

use App\Http\Controllers\OneDriveController;
use App\Http\Middleware\TokenAuth;
use Illuminate\Support\Facades\Route;

Route::middleware(TokenAuth::class)->group(function () {
    Route::post('/mkdir',  [OneDriveController::class, 'mkdir']);
    Route::get('/exists',  [OneDriveController::class, 'exists']);
    Route::get('/list',    [OneDriveController::class, 'list']);
    Route::post('/upload', [OneDriveController::class, 'upload']);
    Route::delete('/delete', [OneDriveController::class, 'delete']);
});

