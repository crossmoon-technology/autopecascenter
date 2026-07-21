<?php

namespace App\Services\Auth;

use App\Enums\Role;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

class AuthService
{
    public function attempt(Request $request): bool
    {
        return Auth::attempt($request->only('email', 'password'), $request->boolean('remember'));
    }

    public function register(RegisterRequest $request): User
    {
        $user = User::create([
            ...$request->only('name', 'email', 'document', 'password', 'registration_ip'),
            'role' => Role::Client,
        ]);

        Auth::login($user);

        return $user;
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function sendResetLink(ForgotPasswordRequest $request): string
    {
        return Password::sendResetLink($request->only('email'));
    }

    public function resetPassword(ResetPasswordRequest $request): string
    {
        return Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                event(new PasswordReset($user));
            }
        );
    }
}
