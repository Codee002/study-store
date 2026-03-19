<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/cache-test', function () {
    Cache::put('ping', 'pong', 60);
    return Cache::get('ping');
});
