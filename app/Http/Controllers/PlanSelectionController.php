<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Requests\Auth\PlanChoiceRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PlanSelectionController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Só faz sentido pra um vendedor que ainda não escolheu plano, ou que já escolheu mas
     * está com a avaliação vencida sem pagamento aprovado (precisa esperar a aprovação pra
     * voltar a ter acesso, ver isAwaitingApproval()) — qualquer outra conta é mandada de
     * volta pro painel correspondente.
     */
    private function canChoosePlan(User $user): bool
    {
        return $user->role === Role::Seller
            && ($user->plan === null || ($user->isTrialExpired() && $user->plan_approved_at === null));
    }

    /**
     * Já escolheu um plano antes (mesmo com a avaliação vencida agora) — não libera acesso
     * de novo nem mostra o formulário completo de escolha (com a opção "avaliação
     * gratuita", já bloqueada pra essa conta), só o aviso de que o representante vai
     * entrar em contato, com a opção de trocar de plano (ver
     * choose-plan-pending-approval.blade.php e AuthService::choosePlan(), que preserva
     * trial_ends_at quando ele já existe).
     */
    private function isAwaitingApproval(User $user): bool
    {
        return $user->plan !== null;
    }

    public function show(): View|RedirectResponse
    {
        $user = Auth::user();

        if (! $this->canChoosePlan($user)) {
            return redirect()->to(FilamentAuthenticate::panelUrlForRole($user->role));
        }

        if ($this->isAwaitingApproval($user)) {
            return view('auth.choose-plan-pending-approval', ['user' => $user]);
        }

        return view('auth.choose-plan');
    }

    /**
     * A avaliação gratuita abre o painel na hora. Escolher Básico ou Profissional
     * também libera o acesso via avaliação gratuita (a cobrança em si não acontece pela
     * plataforma), mas mostra um aviso explicando que um representante entra em contato
     * em até 24h antes de seguir pro painel.
     */
    public function store(PlanChoiceRequest $request): View|RedirectResponse
    {
        $user = Auth::user();

        if (! $this->canChoosePlan($user)) {
            return redirect()->to(FilamentAuthenticate::panelUrlForRole($user->role));
        }

        $wasAwaitingApproval = $this->isAwaitingApproval($user);

        $this->authService->choosePlan($user, $request);

        if ($wasAwaitingApproval) {
            return view('auth.choose-plan-pending-approval', ['user' => $user]);
        }

        if ($request->validated('plan_choice') === 'trial') {
            return redirect()->to(FilamentAuthenticate::panelUrlForRole($user->role));
        }

        return view('auth.choose-plan-confirmation');
    }
}
