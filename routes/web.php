<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'app')->name('home');

Route::fallback(fn () => view('app'))->name('fallback');
