<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::prefix('admin')->group(function () {
    Route::get('{any?}', function () {
        return view('application');
    })->where('any', '.*');
});
