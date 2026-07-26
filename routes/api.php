<?php

use App\Http\Controllers\Api\CatalogImportController;
use Illuminate\Support\Facades\Route;

Route::post('/catalogs/import', [CatalogImportController::class, 'store'])
    ->middleware('catalog-import.api-key')
    ->name('api.catalogs.import');
