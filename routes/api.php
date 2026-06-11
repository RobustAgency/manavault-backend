<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SupabaseController;
use App\Http\Controllers\LoginLogsController;
use App\Http\Controllers\Api\IrewardifyWebhookController;

Route::post('/auth/login', [SupabaseController::class, 'login']);

Route::get('/login-logs', [LoginLogsController::class, 'index']);
Route::post('/login-logs', [LoginLogsController::class, 'store']);

Route::post('/webhooks/irewardify', [IrewardifyWebhookController::class, 'handle']);

require __DIR__.'/users/api.php';
require __DIR__.'/manastore/v1/api.php';
