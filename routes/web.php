<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/login', '/admin/login')->name('login');
Route::redirect('/register', '/admin/login')->name('register');

Route::get('/', function () {
    return view('frontpage');
});
