<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/arreglar-bd', function () {
    DB::statement("SELECT setval('conversations_id_seq', (SELECT COALESCE(MAX(id), 1) FROM conversations));");
    return '¡La secuencia de conversaciones ha sido arreglada con exito!';
});

// Social Auth - Must be web routes (not API) to allow HTTP redirects to OAuth providers
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::get('/auth/github', [AuthController::class, 'redirectToGithub']);
Route::get('/auth/github/callback', [AuthController::class, 'handleGithubCallback']);
