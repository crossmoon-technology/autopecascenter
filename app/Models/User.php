<?php

namespace App\Models;

use App\Enums\Role;
use App\Mail\Auth\ResetPasswordMail;
use App\Models\Quotation\Enums\Status;
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

#[Fillable(['role', 'name', 'email', 'document', 'password', 'logo', 'registration_ip'])]
#[Hidden(['password', 'remember_token'])]
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
        ];
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

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'super-admin' => $this->role === Role::SuperAdmin,
            'admin' => $this->role === Role::Admin,
            'client' => $this->role === Role::Client,
            default => false,
        };
    }

    public function sendPasswordResetNotification($token): void
    {
        Mail::to($this->email)->send(new ResetPasswordMail($this, $token));
    }
}
