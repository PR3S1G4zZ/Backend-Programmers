<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/arreglar-bd', function () {
    DB::statement("SELECT setval('conversations_id_seq', (SELECT COALESCE(MAX(id), 1) FROM conversations));");
    return '¡La secuencia de conversaciones ha sido arreglada con exito!';
});

// Social Auth - Sin middleware de sesión/CSRF para evitar timeout en callbacks
$oauthExcludedMiddleware = [StartSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class];

Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->withoutMiddleware($oauthExcludedMiddleware);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->withoutMiddleware($oauthExcludedMiddleware);
Route::get('/auth/github', [AuthController::class, 'redirectToGithub'])->withoutMiddleware($oauthExcludedMiddleware);
Route::get('/auth/github/callback', [AuthController::class, 'handleGithubCallback'])->withoutMiddleware($oauthExcludedMiddleware);
