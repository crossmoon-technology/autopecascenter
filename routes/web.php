<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlanSelectionController;
use App\Http\Controllers\PublicPartController;
use App\Http\Controllers\SubscriptionStatusController;
use App\Models\Manufacturer;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $manufacturers = Manufacturer::query()
        ->where('is_active', true)
        ->whereNotNull('logo')
        ->get();

    return view('home', ['manufacturers' => $manufacturers]);
})->name('home');

Route::get('/p/{part}', [PublicPartController::class, 'show'])
    ->name('parts.public')
    ->middleware(['signed', 'throttle:60,1']);

Route::get('/confirmar-email/{id}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify')
    ->middleware(['signed', 'throttle:6,1']);

Route::get('/como-funciona', function () {
    return view('como-funciona');
})->name('como-funciona');

Route::get('/planos', function () {
    return view('planos');
})->name('planos');

Route::get('/contato', function () {
    return view('contato');
})->name('contato');

Route::get('/perguntas-frequentes', function () {
    return view('faq');
})->name('perguntas-frequentes');

Route::get('/politica-de-privacidade', function () {
    return view('politica-de-privacidade');
})->name('privacy-policy');

Route::get('/politica-de-cookies', function () {
    return view('politica-de-cookies');
})->name('cookie-policy');

Route::get('/termos-de-uso', function () {
    return view('termos-de-uso');
})->name('terms-of-use');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt')->middleware('throttle:6,1');

    Route::get('/registrar', [AuthController::class, 'showRegisterChoice'])->name('register');

    Route::get('/registrar/vendedor', [AuthController::class, 'showRegisterSeller'])->name('register.seller');
    Route::post('/registrar/vendedor', [AuthController::class, 'registerSeller'])->name('register.seller.attempt');

    Route::get('/registrar/cliente', [AuthController::class, 'showRegisterClient'])->name('register.client');
    Route::post('/registrar/cliente', [AuthController::class, 'registerClient'])->name('register.client.attempt')->middleware('throttle:20,1');

    Route::get('/esqueci-a-senha', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/esqueci-a-senha', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/redefinir-senha/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/redefinir-senha', [AuthController::class, 'resetPassword'])->name('password.update');

    Route::get('/reenviar-confirmacao', [AuthController::class, 'showResendVerification'])->name('verification.resend.show');
    Route::post('/reenviar-confirmacao', [AuthController::class, 'resendVerification'])->name('verification.resend')->middleware('throttle:6,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/escolher-plano', [PlanSelectionController::class, 'show'])->name('choose-plan');
    Route::post('/escolher-plano', [PlanSelectionController::class, 'store'])->name('choose-plan.store');

    Route::get('/assinatura-vencida', [SubscriptionStatusController::class, 'show'])->name('subscription-expired');
});
