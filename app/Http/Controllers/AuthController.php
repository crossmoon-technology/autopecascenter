<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Controllers\AuthController\Exceptions\EmailNotVerifiedException;
use App\Http\Controllers\AuthController\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\AuthController\Exceptions\InvalidResetTokenException;
use App\Http\Controllers\AuthController\Exceptions\ResetLinkException;
use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterClientRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
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

        $user = Auth::user();

        // Todo cadastro na plataforma (Seller ou Client) depende de confirmação de
        // e-mail antes de poder entrar — não é mais só o Seller.
        if (! $user->hasVerifiedEmail()) {
            Auth::logout();
            throw EmailNotVerifiedException::make();
        }

        $request->session()->regenerate();

        // Continua autenticado mesmo bloqueado (avaliação vencida sem plano aprovado, ou
        // assinatura paga vencida) — só não tem acesso a nenhuma funcionalidade do painel
        // (ver RedirectExpiredSellerTrial, que barra isso em qualquer request seguinte, com
        // a mesma lógica baseada em hasActiveSellerAccess() por baixo). Cai direto na tela
        // certa pra poder resolver a situação, em vez de ficar travado num 403 sem saída.
        if ($user->role === Role::Seller && ! $user->hasActiveSellerAccess()) {
            if ($user->plan !== null && $user->plan_approved_at !== null) {
                return redirect()->route('subscription-expired');
            }

            return redirect()->route('choose-plan');
        }

        return redirect()->to(FilamentAuthenticate::panelUrlForRole($user->role));
    }

    /**
     * Primeiro passo do cadastro, comum aos dois papéis — só depois de escolher aqui é
     * que o usuário vê o formulário certo (ver showRegisterSeller()/showRegisterClient()).
     */
    public function showRegisterChoice(): View
    {
        return view('auth.register-choose');
    }

    public function showRegisterSeller(): View
    {
        return view('auth.register');
    }

    public function registerSeller(RegisterRequest $request): RedirectResponse
    {
        $this->authService->registerSeller($request);

        $request->session()->regenerate();

        return redirect()->route('login')->with(
            'status',
            'Cadastro recebido! Enviamos um e-mail de confirmação — confirme seu endereço para poder entrar.'
        );
    }

    public function showRegisterClient(): View
    {
        return view('auth.register-client');
    }

    public function registerClient(RegisterClientRequest $request): RedirectResponse
    {
        $this->authService->registerClient($request);

        return redirect()->route('login')->with(
            'status',
            'Cadastro recebido! Enviamos um e-mail de confirmação — confirme seu endereço para poder entrar.'
        );
    }

    /**
     * A rota já está protegida pelo middleware `signed` — qualquer adulteração no id
     * invalida a assinatura antes mesmo de chegar aqui. É esse link, e só ele, que prova
     * que quem está confirmando é o dono de verdade do e-mail — por isso é aqui, e não em
     * registerSeller(), que um upgrade de Cliente pra Vendedor pendente é efetivado (ver
     * AuthService::applyPendingSellerUpgradeIfAny()).
     */
    public function verifyEmail(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $this->authService->applyPendingSellerUpgradeIfAny($user);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->route('login')->with('status', 'E-mail confirmado! Faça login para continuar.');
    }

    public function showResendVerification(): View
    {
        return view('auth.resend-verification');
    }

    public function resendVerification(ResendVerificationRequest $request): RedirectResponse
    {
        $this->authService->resendVerificationEmail($request);

        return back()->with(
            'status',
            'Se o e-mail informado tiver um cadastro pendente de confirmação, reenviamos o link agora.'
        );
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
