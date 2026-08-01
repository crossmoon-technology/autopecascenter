<?php

namespace App\Models;

use App\Enums\Role;
use App\Mail\Auth\ResetPasswordMail;
use App\Models\Quotation\Enums\Status;
use App\Models\User\Enums\Plan;
use database\factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['role', 'name', 'email', 'document', 'password', 'logo', 'registration_ip', 'plan', 'trial_ends_at', 'subscription_ends_at'])]
#[Hidden(['password', 'remember_token', 'pending_seller_upgrade'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'tutorial_completed_at' => 'datetime',
            'lgpd_accepted_at' => 'datetime',
            'plan' => Plan::class,
            'trial_ends_at' => 'datetime',
            'plan_approved_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'pending_seller_upgrade' => 'array',
        ];
    }

    /**
     * Todo Role::Seller ganha um código único automaticamente ao ser criado — é o que o
     * cliente informa no cadastro direto (/registrar/cliente) pra se vincular a esse
     * vendedor (ver AuthService::registerClient()). De propósito fora do Fillable, pra
     * não virar algo setável via formulário.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if ($user->role === Role::Seller && $user->referral_code === null) {
                $user->referral_code = self::generateUniqueReferralCode();
            }
        });

        static::deleting(function (User $user): void {
            // Arquivo só é removido do disco na exclusão permanente — o soft delete
            // precisa manter o arquivo, já que a conta pode ser restaurada depois.
            if ($user->isForceDeleting() && filled($user->logo)) {
                Storage::disk('public')->delete($user->logo);
            }
        });
    }

    public static function generateUniqueReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (static::query()->where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * tutorial_completed_at é gerenciado só pelo próprio tutorial (ver App\Livewire\OnboardingTutorial)
     * — de propósito fora do Fillable, pra não virar algo setável via formulário.
     */
    public function needsTutorial(): bool
    {
        return $this->tutorial_completed_at === null;
    }

    public function markTutorialCompleted(): void
    {
        $this->forceFill(['tutorial_completed_at' => now()])->save();
    }

    /**
     * lgpd_accepted_at é gerenciado só pelo próprio modal de consentimento (ver
     * App\Livewire\LgpdConsent) — de propósito fora do Fillable, pra não virar algo
     * setável via formulário. O tutorial (ver needsTutorial() acima) só começa depois
     * desse aceite — ver App\Livewire\OnboardingTutorial::mount()/startAfterLgpdConsent().
     */
    public function needsLgpdConsent(): bool
    {
        return $this->lgpd_accepted_at === null;
    }

    public function acceptLgpd(): void
    {
        $this->forceFill(['lgpd_accepted_at' => now()])->save();
    }

    public function preferredManufacturers(): BelongsToMany
    {
        return $this->belongsToMany(Manufacturer::class, 'user_manufacturer_preferences')->withTimestamps();
    }

    public function searchHistory(): HasMany
    {
        return $this->hasMany(SearchHistory::class);
    }

    public function favoriteLists(): HasMany
    {
        return $this->hasMany(FavoriteList::class);
    }

    /**
     * Toda conta tem uma dessas, criada na hora que precisar — é pra onde vai uma peça
     * favoritada quando o usuário não escolhe uma lista específica, e não pode ser
     * apagada (ver FavoriteList::isDeletable()).
     */
    public function defaultFavoriteList(): FavoriteList
    {
        return $this->favoriteLists()->firstOrCreate(
            ['is_default' => true],
            ['name' => 'Lista padrão'],
        );
    }

    /**
     * @return Collection<int, int>
     */
    public function favoritedPartIds(): Collection
    {
        return Part::query()
            ->whereHas('favoriteLists', fn ($query) => $query->where('user_id', $this->id))
            ->pluck('id');
    }

    /**
     * Usado pelo toggle rápido (estrela): "desfavoritar" remove a peça de TODAS as
     * listas do usuário de uma vez, não só da lista padrão — senão uma peça adicionada
     * também a uma lista específica continuaria "favoritada" ali sem a estrela refletir isso.
     */
    public function detachPartFromAllFavoriteLists(int $part_id): void
    {
        FavoriteList::query()
            ->where('user_id', $this->id)
            ->each(fn (FavoriteList $favoriteList) => $favoriteList->parts()->detach($part_id));
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * A cotação aberta do usuário, sem criar uma nova se não houver — usado por lugares
     * que só precisam LER o carrinho atual (ex: widget do topbar, marcar peças já
     * adicionadas), pra não gerar uma cotação em branco só de a página ser renderizada.
     *
     * updated_at só tem precisão de segundo, então dois toques na mesma cotação dentro
     * do mesmo segundo empatam — o id como desempate garante que o toque mais recente
     * (maior id entre os empatados) sempre vence.
     */
    public function openQuotationOrNull(): ?Quotation
    {
        return $this->quotations()->where('status', Status::Open)->latest('updated_at')->latest('id')->first();
    }

    /**
     * A cotação usada como "carrinho" pelas ações de adicionar peça espalhadas pela Base
     * de dados, API, Iframes e Favoritos — cria uma nova em branco se não houver nenhuma
     * aberta ainda, já que aqui a intenção é realmente adicionar um item a ela.
     */
    public function openQuotation(): Quotation
    {
        return $this->openQuotationOrNull() ?? $this->quotations()->create(['status' => Status::Open]);
    }

    /**
     * Os vendedores aos quais essa conta (Role::Client) está vinculada — um cliente pode
     * aceitar convites de vários vendedores diferentes, cada um vendo só os próprios
     * dados dele (ver client_sellers). Sem sentido pra contas Role::Seller/SuperAdmin.
     */
    public function sellers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_sellers', 'client_id', 'seller_id')->withTimestamps();
    }

    /**
     * Os clientes vinculados a essa conta (Role::Seller) — o inverso de sellers().
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_sellers', 'seller_id', 'client_id')->withTimestamps();
    }

    public function isLinkedToSeller(self $seller): bool
    {
        return $this->sellers()->whereKey($seller->id)->exists();
    }

    /**
     * Idempotente de propósito — vincular a um vendedor que já é seu não duplica a linha
     * (ver unique(client_id, seller_id) na migration).
     */
    public function linkToSeller(self $seller): void
    {
        $this->sellers()->syncWithoutDetaching([$seller->id]);
    }

    /**
     * Histórico de pedidos enviados por essa conta convidada — pode enviar vários ao
     * longo do tempo, não só um.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Uma conta Role::Client que vira Role::Seller (ver AuthService::registerSeller())
     * continua com o mesmo id — os pedidos e vendedores que ela já tinha como cliente
     * não somem, só deixam de ser acessíveis pelo painel de cliente (o role mudou) e
     * passam a aparecer aqui mesmo, no painel de vendedor, através do menu "Cliente" (ver
     * App\Filament\Pages\Cliente\*). sellers() é o sinal de que isso aconteceu: só existe
     * linha nele pra quem já foi Role::Client em algum momento.
     */
    public function hasClientHistory(): bool
    {
        return $this->sellers()->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'super-admin' => $this->role === Role::SuperAdmin,
            'admin' => $this->role === Role::Seller && $this->hasActiveSellerAccess(),
            'client' => $this->role === Role::Client,
            default => false,
        };
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * email_verified_at é gerenciado só pelo link assinado enviado no cadastro (ver
     * AuthController::verifyEmail()) — de propósito fora do Fillable.
     */
    public function markEmailAsVerified(): void
    {
        $this->forceFill(['email_verified_at' => now()])->save();
    }

    /**
     * A avaliação gratuita é sempre automática — só o pagamento de um plano precisa de
     * aprovação manual do SuperAdmin, já que a cobrança não acontece pela plataforma (ver
     * SellerResource, ação "Aprovar pagamento"). Vendedor sem plano escolhido ainda
     * (plan null) não conta como "pagamento pendente": ele nem chegou nessa etapa. Também
     * volta a valer quando uma assinatura já aprovada vence (isSubscriptionExpired()) — o
     * botão "Aprovar pagamento" reaparece pra renovar, já que approvePlanPayment() serve
     * tanto pra primeira aprovação quanto pra renovação.
     */
    public function isPaymentPending(): bool
    {
        return $this->role === Role::Seller
            && $this->plan !== null
            && ($this->plan_approved_at === null || $this->isSubscriptionExpired());
    }

    /**
     * trial_ends_at é preenchido assim que o vendedor escolhe qualquer um dos 3 planos na
     * tela de escolha (ver PlanSelectionController) — mesmo quem escolhe Básico ou
     * Profissional entra automaticamente em avaliação gratuita de 7 dias, já que a
     * cobrança em si só é confirmada depois pelo contato do representante.
     */
    public function isTrialExpired(): bool
    {
        return $this->role === Role::Seller
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast();
    }

    /**
     * subscription_ends_at só é preenchido junto da aprovação de pagamento (ver
     * approvePlanPayment()) — assinatura sem pagamento nunca aprovado (ainda em avaliação
     * ou com avaliação vencida sem aprovação) não conta como "vencida" aqui, isso é
     * isTrialExpired().
     */
    public function isSubscriptionExpired(): bool
    {
        return $this->role === Role::Seller
            && $this->plan_approved_at !== null
            && $this->subscription_ends_at !== null
            && $this->subscription_ends_at->isPast();
    }

    /**
     * Acesso ao painel exige plano escolhido e (avaliação gratuita ainda rodando OU
     * pagamento aprovado com a assinatura de 30 dias ainda dentro do prazo) — diferente de
     * antes, o pagamento aprovado NÃO vale pra sempre: vence em 30 dias e precisa de nova
     * aprovação do SuperAdmin pra renovar (ver approvePlanPayment()).
     */
    public function hasActiveSellerAccess(): bool
    {
        return $this->role === Role::Seller
            && $this->plan !== null
            && (! $this->isTrialExpired() || ($this->plan_approved_at !== null && ! $this->isSubscriptionExpired()));
    }

    /**
     * plan_approved_at é gerenciado só pela ação "Aprovar pagamento" do SellerResource —
     * de propósito fora do Fillable, pra não virar algo setável via formulário direto.
     * subscription_ends_at é preenchido automaticamente aqui, mas continua editável à mão
     * no formulário (ver SellerForm), pra o SuperAdmin poder ajustar a data manualmente
     * (promoção, correção etc) sem precisar clicar em "Aprovar pagamento" de novo. Usada
     * tanto pra primeira aprovação quanto pra renovar uma assinatura vencida — sempre
     * concede mais 30 dias a partir de agora, não acumula com o prazo anterior.
     */
    public function approvePlanPayment(): void
    {
        $this->forceFill([
            'plan_approved_at' => now(),
            'subscription_ends_at' => now()->addDays(30),
        ])->save();
    }

    /**
     * subscription_ends_at nulo com pagamento aprovado significa "sem expiração
     * automática" — mesma semântica de trial_ends_at nulo (ver SellerForm) — e cobre
     * contas aprovadas antes desse campo existir, que nunca tiveram a data preenchida.
     */
    public function sellerStatusLabel(): string
    {
        return match (true) {
            ! $this->hasVerifiedEmail() => 'E-mail não confirmado',
            $this->plan === null => 'Aguardando escolha de plano',
            $this->isTrialExpired() && $this->plan_approved_at === null => 'Avaliação expirada',
            // trial_ends_at nulo aqui não é "vencido" (isTrialExpired() já cobre isso
            // acima) — é um plano atribuído sem uma avaliação com prazo definido, ex: uma
            // conta criada/editada direto pelo SuperAdmin (ver UserForm) sem preencher
            // essa data.
            $this->plan_approved_at === null && $this->trial_ends_at === null => 'Em avaliação',
            $this->plan_approved_at === null => 'Em avaliação até '.$this->trial_ends_at->format('d/m/Y'),
            $this->isSubscriptionExpired() => 'Assinatura vencida',
            $this->subscription_ends_at === null => 'Ativo',
            default => 'Ativo até '.$this->subscription_ends_at->format('d/m/Y'),
        };
    }

    public function sellerStatusColor(): string
    {
        return match (true) {
            ! $this->hasVerifiedEmail() => 'gray',
            $this->plan === null => 'warning',
            $this->isTrialExpired() && $this->plan_approved_at === null => 'danger',
            $this->plan_approved_at === null => 'info',
            $this->isSubscriptionExpired() => 'danger',
            default => 'success',
        };
    }

    public function sendPasswordResetNotification($token): void
    {
        Mail::to($this->email)->send(new ResetPasswordMail($this, $token));
    }
}
