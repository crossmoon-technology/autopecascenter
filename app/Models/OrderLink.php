<?php

namespace App\Models;

use App\Models\Order\Enums\Status;
use database\factories\OrderLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'label', 'token', 'used_at', 'registered_user_id'])]
class OrderLink extends Model
{
    /** @use HasFactory<OrderLinkFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A conta (Role::Client, restrita a um único pedido) criada quando alguém se
     * cadastra através desse link — nula até o link ser usado.
     */
    public function registeredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_user_id');
    }

    public function displayLabel(): string
    {
        return $this->label ?: "Link #{$this->id}";
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function statusLabel(): string
    {
        return $this->isUsed() ? 'Cadastrado' : 'Aguardando cadastro';
    }

    public function statusColor(): string
    {
        return $this->isUsed() ? 'success' : 'warning';
    }

    /**
     * Quantos pedidos desse cliente ainda estão em andamento — pending ou processing,
     * excluindo os já resolvidos (finished ou cancelled, ver App\Models\Order\Enums\Status)
     * — usado pra coluna "Pedidos pendentes" na listagem de clientes.
     */
    public function pendingOrdersCount(): int
    {
        $registeredUser = $this->registeredUser;

        if (! $registeredUser) {
            return 0;
        }

        $settled = [Status::Finished, Status::Cancelled];

        return $registeredUser->relationLoaded('orders')
            ? $registeredUser->orders->whereNotIn('status', $settled)->count()
            : $registeredUser->orders()->whereNotIn('status', $settled)->count();
    }

    public function hasToken(): bool
    {
        return $this->token !== null;
    }

    public function publicUrl(): ?string
    {
        return $this->hasToken()
            ? route('order-links.show', ['orderLink' => $this->token])
            : null;
    }

    /**
     * O token (e portanto o link em si) só é gerado quando o vendedor clica em "Gerar
     * link" na página do cliente (ver App\Filament\Pages\Buscas\ViewOrderLink) — criar o
     * registro (ver App\Filament\Pages\Buscas\OrderLinks) não gera um automaticamente,
     * já que nem todo cliente cadastrado precisa de um link de verdade pra enviar.
     */
    public function generateToken(): void
    {
        $this->update(['token' => Str::random(48)]);
    }

    /**
     * Reivindica o link atomicamente — o `whereNull('used_at')` na condição do update
     * garante que, mesmo que dois requests de cadastro cheguem quase juntos pro mesmo
     * link, só um consegue de fato marcá-lo como usado (o outro afeta 0 linhas e sabe
     * que perdeu a corrida). De propósito não recebe o usuário criado aqui — só depois
     * que a reivindicação é confirmada é que a conta é criada, pra não sobrar um
     * usuário órfão se a corrida for perdida.
     */
    public function claim(): bool
    {
        return static::query()
            ->whereKey($this->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]) === 1;
    }
}
