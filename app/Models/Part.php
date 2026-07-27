<?php

namespace App\Models;

use database\factories\PartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

#[Fillable(['catalog_id', 'codigo', 'conversoes', 'atributos'])]
class Part extends Model
{
    /** @use HasFactory<PartFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'conversoes' => 'array',
            'atributos' => 'array',
        ];
    }

    /**
     * Todo código (próprio e de conversoes) é normalizado — maiúsculo, sem espaço —
     * antes de salvar, não só na importação/atualização de catálogo: cobre qualquer
     * caminho que crie/edite uma Part (inclusive futuro), sem depender de cada chamador
     * lembrar de normalizar. A busca usa a mesma normalização (ver
     * App\Filament\Pages\Buscas\CatalogDatabaseSearch e
     * App\Services\PartEquivalence\RebuildPartEquivalences) pra continuar batendo.
     */
    protected static function booted(): void
    {
        static::saving(function (Part $part): void {
            $part->codigo = self::normalizeCode($part->codigo);
            $part->conversoes = self::normalizeConversoes($part->conversoes);
        });
    }

    public static function normalizeCode(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($value)));
    }

    /**
     * Normaliza só os valores-folha (os códigos em si) de uma estrutura de conversoes,
     * preservando as chaves (nome da marca, ex: "NAKATA") intactas — não são códigos.
     */
    public static function normalizeConversoes(mixed $value): mixed
    {
        if (is_array($value)) {
            return collect($value)->map(fn ($item) => self::normalizeConversoes($item))->all();
        }

        if ($value === null) {
            return null;
        }

        return self::normalizeCode((string) $value);
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
     * Pareamento direto pré-computado (ver App\Services\PartEquivalence\RebuildPartEquivalences),
     * recalculado a cada import/atualização de catálogo — nunca em tempo de busca. As
     * duas direções são gravadas em part_equivalences, então essa relação já é simétrica
     * sem precisar de OR/UNION.
     */
    public function equivalentParts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'part_equivalences', 'part_id', 'equivalent_part_id')->withTimestamps();
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
