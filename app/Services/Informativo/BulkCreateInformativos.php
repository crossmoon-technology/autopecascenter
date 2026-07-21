<?php

namespace App\Services\Informativo;

use App\Models\Catalog;
use App\Models\Informativo\Enums\InformativoType;

class BulkCreateInformativos
{
    /**
     * @param  array<int, string>  $files  disk paths of the already-uploaded files
     * @param  array<string, string>  $original_names  disk path => original filename
     */
    public function handle(Catalog $catalog, array $files, array $original_names): void
    {
        foreach ($files as $path) {
            $catalog->informativos()->create([
                'file' => $path,
                'original_name' => $original_names[$path] ?? basename($path),
                'type' => InformativoType::fromExtension(pathinfo($path, PATHINFO_EXTENSION)),
            ]);
        }
    }
}
