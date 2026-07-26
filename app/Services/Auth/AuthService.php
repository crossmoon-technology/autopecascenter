<?php

namespace App\Services\Auth;

use App\Enums\Role;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\PlanChoiceRequest;
use App\Http\Requests\Auth\RegisterClientRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Mail\Auth\VerifyEmailMail;
use App\Models\User;
use App\Models\User\Enums\Plan;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class AuthService
{
    public function attempt(Request $request): bool
    {
        return Auth::attempt($request->only('email', 'password'), $request->boolean('remember'));
    }

    /**
     * O cadastro público (`/registrar/vendedor`, escolhido em `/registrar`) é pra
     * lojas/vendedores se inscreverem na plataforma — por isso role = Seller. Contas
     * Role::Client se cadastram por conta própria em `/registrar/cliente` (ver
     * registerClient() abaixo), informando o código do vendedor a que querem se vincular.
     *
     * Um e-mail que já pertence a uma conta Role::Client vira upgrade da MESMA conta pra
     * Seller (RegisterRequest permite isso de propósito) — não cria uma segunda conta.
     * O upgrade em si (role, nome, documento, senha) só é aplicado depois que o e-mail é
     * confirmado (ver applyPendingSellerUpgradeIfAny(), chamado por
     * AuthController::verifyEmail()) — fica só "guardado" em pending_seller_upgrade até
     * lá, de propósito: sem essa confirmação, qualquer um poderia trocar a senha e o role
     * de uma conta Cliente alheia só sabendo o e-mail dela, sem provar que é o dono. Até
     * confirmar, a conta continua funcionando normalmente como Role::Client, com a senha
     * antiga — nada muda visivelmente pra ela.
     *
     * Uma conta nova (sem e-mail já cadastrado) nasce sem plano e sem e-mail confirmado
     * — o vendedor só escolhe um plano (ver PlanSelectionController) depois de confirmar
     * o e-mail e logar. Sem login automático aqui de propósito: o e-mail ainda não foi
     * confirmado, então autenticar já na hora do cadastro só deixaria uma sessão presa
     * sem conseguir entrar em painel nenhum.
     */
    public function registerSeller(RegisterRequest $request): User
    {
        $existingClient = User::where('email', $request->validated('email'))
            ->where('role', Role::Client)
            ->first();

        if ($existingClient) {
            $existingClient->forceFill([
                'pending_seller_upgrade' => [
                    'name' => $request->validated('name'),
                    'document' => $request->validated('document'),
                    'password' => Hash::make($request->validated('password')),
                    'registration_ip' => $request->validated('registration_ip'),
                ],
            ])->save();

            Mail::to($existingClient->email)->send(new VerifyEmailMail($existingClient));

            return $existingClient;
        }

        $user = User::create([
            ...$request->only('name', 'email', 'document', 'password', 'registration_ip'),
            'role' => Role::Seller,
        ]);

        Mail::to($user->email)->send(new VerifyEmailMail($user));

        return $user;
    }

    /**
     * Só chamado depois que o e-mail já foi confirmado (ver
     * AuthController::verifyEmail()) — é o que de fato transforma a conta Role::Client
     * em Role::Seller, nunca antes disso. A senha em pending_seller_upgrade já está com
     * hash (ver registerSeller() acima), então não passa de novo por Hash::make() aqui —
     * o cast 'hashed' do User detecta isso sozinho (ver Hash::isHashed()).
     */
    public function applyPendingSellerUpgradeIfAny(User $user): void
    {
        if ($user->pending_seller_upgrade === null) {
            return;
        }

        $pending = $user->pending_seller_upgrade;

        $user->forceFill([
            'name' => $pending['name'],
            'document' => $pending['document'],
            'password' => $pending['password'],
            'registration_ip' => $pending['registration_ip'],
            'role' => Role::Seller,
            'referral_code' => User::generateUniqueReferralCode(),
            'pending_seller_upgrade' => null,
        ])->save();
    }

    /**
     * Igual ao cadastro de vendedor, também exige confirmação de e-mail antes de poder
     * entrar (ver AuthController::login()) — sem login automático aqui de propósito,
     * pelo mesmo motivo: autenticar antes da confirmação só deixaria uma sessão presa.
     */
    public function registerClient(RegisterClientRequest $request): User
    {
        $seller = $request->seller();

        $user = User::create([
            ...$request->only('name', 'email', 'document', 'password'),
            'role' => Role::Client,
        ]);

        $user->linkToSeller($seller);

        Mail::to($user->email)->send(new VerifyEmailMail($user));

        return $user;
    }

    /**
     * As 3 opções da tela de escolha de plano — mesmo Básico/Profissional entram
     * automaticamente na avaliação gratuita de 7 dias, já que a cobrança em si não
     * acontece pela plataforma (ver User::isPaymentPending()/SellerResource). O plano
     * "avaliação gratuita" em si é sempre registrado como Profissional.
     *
     * trial_ends_at só é setado se ainda for null (primeira escolha de plano) — reescolher
     * um plano pago depois da avaliação já vencida NÃO gera mais dias de acesso, só troca o
     * plano registrado; o vendedor fica sem acesso até o SuperAdmin aprovar o pagamento (ver
     * PlanSelectionController::store(), que mostra o aviso certo pra esse caso).
     */
    public function choosePlan(User $user, PlanChoiceRequest $request): void
    {
        $plan = match ($request->validated('plan_choice')) {
            'trial', 'profissional' => Plan::Profissional,
            'basico' => Plan::Basico,
        };

        $user->update([
            'plan' => $plan,
            'trial_ends_at' => $user->trial_ends_at ?? now()->addDays(7),
        ]);
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
