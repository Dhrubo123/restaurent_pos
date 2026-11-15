<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $spaPath = public_path('app.html');
    if (file_exists($spaPath)) {
        return response(file_get_contents($spaPath), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    return view('welcome');
});

Route::fallback(function () {
    $spaPath = public_path('app.html');
    if (file_exists($spaPath)) {
        return response(file_get_contents($spaPath), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    return view('welcome');
});
