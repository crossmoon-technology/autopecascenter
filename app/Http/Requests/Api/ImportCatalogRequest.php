<?php

namespace App\Http\Requests\Api;

use App\Models\Catalog;
use App\Rules\ValidJsonl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is already gated by the catalog-import.api-key middleware, not per-user policies.
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                Rule::exists('catalogs', 'slug')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $catalog = Catalog::query()->where('slug', $value)->first();

                    // Catálogo com scraper_slug é gerenciado pelo ScrapeCatalogs — o arquivo
                    // é sobrescrito a cada execução, então um import manual aqui seria
                    // descartado na próxima reimportação automática (ou pior, corromperia
                    // o skip-se-inalterado ao trocar o file sem atualizar source_version).
                    if ($catalog !== null && filled($catalog->scraper_slug)) {
                        $fail('Este catálogo é gerenciado automaticamente por um provedor de scraping e não aceita importação manual de arquivo.');
                    }
                },
            ],
            'file' => ['required', 'file', 'extensions:jsonl', new ValidJsonl],
        ];
    }
}
