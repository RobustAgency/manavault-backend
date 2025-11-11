<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SupabaseController;

Route::post('/auth/login', [SupabaseController::class, 'login']);

require __DIR__.'/admins/api.php';
require __DIR__.'/users/api.php';
