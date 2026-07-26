<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Middleware\FilamentAuthenticate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SubscriptionStatusController extends Controller
{
    /**
     * Só faz sentido pra um vendedor com a assinatura paga vencida (ver
     * User::isSubscriptionExpired()) — não há nada pro vendedor "fazer" aqui além de
     * esperar, a renovação é sempre manual pelo SuperAdmin (ver SellerResource, ação
     * "Aprovar pagamento"). Qualquer outra conta é mandada de volta pro painel
     * correspondente.
     */
    public function show(): View|RedirectResponse
    {
        $user = Auth::user();

        if (! ($user->role === Role::Seller && $user->isSubscriptionExpired())) {
            return redirect()->to(FilamentAuthenticate::panelUrlForRole($user->role));
        }

        return view('auth.subscription-expired', ['user' => $user]);
    }
}
