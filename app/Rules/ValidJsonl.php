<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ValidJsonl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $content = file_get_contents($value->getRealPath());

        if ($content === false) {
            $fail('Não foi possível ler o arquivo.');

            return;
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $fail('O arquivo não pode conter um BOM UTF-8.');

            return;
        }

        if (str_contains($content, "\r")) {
            $fail('O arquivo deve usar quebras de linha LF, sem CR/CRLF.');

            return;
        }

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            json_decode($line);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail('O arquivo contém uma linha que não é um JSON válido.');

                return;
            }
        }
    }
}
