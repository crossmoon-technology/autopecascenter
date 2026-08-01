<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportCatalogRequest;
use App\Jobs\ImportCatalogParts;
use App\Jobs\ImportCatalogPartsUpdate;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Services\Informativo\BulkCreateInformativos;
use Illuminate\Http\JsonResponse;

class CatalogImportController extends Controller
{
    public function store(ImportCatalogRequest $request): JsonResponse
    {
        $catalog = Catalog::query()->where('slug', $request->string('slug'))->firstOrFail();

        $catalog->forceFill(['import_status' => ImportStatus::Importing])->save();

        // Um catálogo sem arquivo original ainda não passou pelo import inicial — o
        // arquivo enviado aqui vira esse arquivo original (ImportCatalogParts, que
        // sobrescreve/ativa o catálogo), não uma atualização aditiva.
        if (blank($catalog->file)) {
            $path = $request->file('file')->store('catalogs', 'local');
            $catalog->forceFill(['file' => $path])->save();

            ImportCatalogParts::dispatch($catalog);
        } else {
            $path = $request->file('file')->store('catalogs/updates', 'local');

            ImportCatalogPartsUpdate::dispatch($catalog, $path);
        }

        if ($request->hasFile('informativos')) {
            $files = [];
            $original_names = [];

            foreach ($request->file('informativos') as $upload) {
                $stored = $upload->store('catalogs/informativos', 'public');
                $files[] = $stored;
                $original_names[$stored] = $upload->getClientOriginalName();
            }

            app(BulkCreateInformativos::class)->handle($catalog, $files, $original_names);
        }

        return response()->json([
            'message' => 'Importação iniciada — as peças novas serão processadas em segundo plano.',
            'catalog' => [
                'id' => $catalog->id,
                'slug' => $catalog->slug,
                'import_status' => $catalog->import_status->label(),
            ],
        ], 202);
    }
}
