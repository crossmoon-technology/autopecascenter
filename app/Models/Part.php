<?php

namespace App\Models;

use database\factories\PartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\URL;

#[Fillable(['catalog_id', 'codigo', 'conversoes', 'atributos'])]
class Part extends Model
{
    /** @use HasFactory<PartFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'conversoes' => 'array',
            'atributos' => 'array',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    public function favoriteLists(): BelongsToMany
    {
        return $this->belongsToMany(FavoriteList::class, 'favorite_list_part')->withTimestamps()->withPivot('note');
    }

    /**
     * URL pública assinada (sem exigir login) — nada é gravado no banco pra gerar isso,
     * é só a assinatura HMAC (baseada na APP_KEY) sobre a própria URL. Qualquer alteração
     * no id invalida a assinatura, então não dá pra "adivinhar" a URL de outra peça
     * trocando o número; e como não persiste nada, é seguro chamar isso a vontade
     * (inclusive pra todo resultado de busca) sem criar exposição que ninguém pediu.
     * Expira sozinho depois de 90 dias, então um link vazado não fica valendo pra sempre.
     */
    public function publicShareUrl(): string
    {
        return URL::temporarySignedRoute(
            'parts.public',
            now()->addDays(90),
            ['part' => $this->id],
        );
    }
}
