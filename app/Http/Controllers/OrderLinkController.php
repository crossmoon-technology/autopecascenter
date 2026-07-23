<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Filament\Client\Pages\CreateOrder;
use App\Http\Requests\OrderLinks\RegisterViaOrderLinkRequest;
use App\Models\OrderLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrderLinkController extends Controller
{
    /**
     * Página pública do link de convite, sem exigir login. Um link só mostra o
     * formulário de cadastro enquanto `used_at` estiver nulo — depois de usado (uma
     * conta já foi criada através dele), vira só uma mensagem, e não dá pra criar
     * outra conta com o mesmo link.
     */
    public function show(OrderLink $orderLink): View
    {
        return view('order-links.show', [
            'orderLink' => $orderLink,
        ]);
    }

    public function store(RegisterViaOrderLinkRequest $request, OrderLink $orderLink): RedirectResponse
    {
        if ($orderLink->isUsed()) {
            return redirect()->route('order-links.show', ['orderLink' => $orderLink->token]);
        }

        // claim() garante que, em caso de dois requests quase simultâneos pro mesmo
        // link, só um consegue reivindicá-lo — a conta só é criada depois de confirmar
        // que ganhou a corrida, pra não sobrar um usuário órfão se perder.
        if (! $orderLink->claim()) {
            return redirect()->route('order-links.show', ['orderLink' => $orderLink->token]);
        }

        $user = User::create([
            ...$request->only('name', 'email', 'document', 'password'),
            'role' => Role::Client,
            'invited_by_id' => $orderLink->user_id,
        ]);

        $orderLink->update(['registered_user_id' => $user->id]);

        Auth::login($user);

        return redirect()->to(CreateOrder::getUrl(panel: 'client'));
    }
}
