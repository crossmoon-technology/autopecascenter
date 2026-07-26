<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidCatalogImportApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected_key = config('services.catalog_import.api_key');
        $given_key = $request->header('X-Api-Key');

        if (blank($expected_key) || blank($given_key) || ! hash_equals($expected_key, $given_key)) {
            abort(401, 'Chave de API inválida.');
        }

        return $next($request);
    }
}
