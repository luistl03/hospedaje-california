<?php

use Illuminate\Support\Facades\Route;

Route::get('/inicio', function () {
    return view('inicio.index');
})->middleware('auth')->name('inicio');

require __DIR__.'/auth.php';