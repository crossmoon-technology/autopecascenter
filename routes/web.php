<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicPartController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/p/{part}', [PublicPartController::class, 'show'])
    ->name('parts.public')
    ->middleware(['signed', 'throttle:60,1']);

Route::get('/como-funciona', function () {
    return view('como-funciona');
})->name('como-funciona');

Route::get('/planos', function () {
    return view('planos');
})->name('planos');

Route::get('/contato', function () {
    return view('contato');
})->name('contato');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt')->middleware('throttle:6,1');

    Route::get('/registrar', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/registrar', [AuthController::class, 'register'])->name('register.attempt');

    Route::get('/esqueci-a-senha', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/esqueci-a-senha', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/redefinir-senha/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/redefinir-senha', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
