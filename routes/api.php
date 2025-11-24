<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SupabaseController;
use App\Http\Controllers\LoginLogsController;

Route::post('/auth/login', [SupabaseController::class, 'login']);

Route::get('/login-logs', [LoginLogsController::class, 'index']);
Route::post('/login-logs', [LoginLogsController::class, 'store']);

require __DIR__.'/admins/api.php';
require __DIR__.'/users/api.php';
