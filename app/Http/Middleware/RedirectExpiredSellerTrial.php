<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roda antes do FilamentAuthenticate no painel do vendedor (ver AdminPanelProvider) —
 * sem isso, um vendedor bloqueado NO MEIO da sessão (avaliação ou assinatura vencendo) só
 * veria o 403 genérico do Filament (User::canAccessPanel() já bloqueia). Aqui a gente
 * intercepta antes e manda pra tela certa, sem deslogar — o vendedor continua autenticado
 * (ver AuthController::login(), mesma lógica já na hora de logar), só não acessa nenhuma
 * funcionalidade do painel até resolver.
 *
 * A checagem parte sempre de hasActiveSellerAccess() (a mesma fonte de verdade usada por
 * User::canAccessPanel()) em vez de reimplementar as condições de trial/assinatura aqui —
 * checar isSubscriptionExpired() direto, sem isso, mandaria pra tela errada num caso raro
 * mas real: um SuperAdmin que estende trial_ends_at manualmente (ver SellerForm) pra além
 * da assinatura vencida ainda deve liberar acesso via o trial, não travar em "assinatura
 * vencida".
 */
class RedirectExpiredSellerTrial
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->role === Role::Seller && ! $user->hasActiveSellerAccess()) {
            if ($user->plan !== null && $user->plan_approved_at !== null) {
                return redirect()->route('subscription-expired');
            }

            return redirect()->route('choose-plan');
        }

        return $next($request);
    }
}
