<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Swagger UI over the hand-written contract in public/openapi.yaml.
Route::view('/docs', 'docs');
