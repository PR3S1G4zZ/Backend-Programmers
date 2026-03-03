<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');

Route::get('/arreglar-bd', function () {
    DB::statement("SELECT setval('conversations_id_seq', (SELECT COALESCE(MAX(id), 1) FROM conversations));");
    return '¡La secuencia de conversaciones ha sido arreglada con exito!';
});

});
