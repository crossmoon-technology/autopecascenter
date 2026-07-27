<?php

namespace App\Http\Requests\Api;

use App\Rules\ValidJsonl;
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
            ],
            'file' => ['required', 'file', 'extensions:jsonl', new ValidJsonl],
            'informativos' => ['sometimes', 'array'],
            // Keep in sync with the 25600 KB (25M) cap in InformativosTable/InformativosRelationManager.
            'informativos.*' => ['file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:25600'],
        ];
    }
}
