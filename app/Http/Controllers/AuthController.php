<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AuthController\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\AuthController\Exceptions\InvalidResetTokenException;
use App\Http\Controllers\AuthController\Exceptions\ResetLinkException;
use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! $this->authService->attempt($request)) {
            throw InvalidCredentialsException::make();
        }

        $request->session()->regenerate();

        return redirect()->intended(FilamentAuthenticate::panelUrlForRole(Auth::user()->role));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $this->authService->register($request);

        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout($request);

        return redirect()->route('login');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $status = $this->authService->sendResetLink($request);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ResetLinkException::make($status);
        }

        return back()->with('status', __($status));
    }

    public function showResetPassword(string $token): View
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        $status = $this->authService->resetPassword($request);

        if ($status !== Password::PASSWORD_RESET) {
            throw InvalidResetTokenException::make($status);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
