<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Services\QrCode\QrCodeGenerator;
use Illuminate\View\View;

class PublicPartController extends Controller
{
    /**
     * Página pública de uma peça, sem exigir login. A rota já está protegida pelo
     * middleware `signed`: qualquer adulteração no id invalida a assinatura antes
     * mesmo de chegar aqui (403), então não dá pra enumerar peças trocando o número
     * na URL. Além disso, some silenciosamente (404) se o catálogo/fabricante estiver
     * inativo, pra não expor uma peça que já não devia estar visível em lugar nenhum.
     */
    public function show(Part $part, QrCodeGenerator $qrCodeGenerator): View
    {
        $part->loadMissing('catalog.manufacturer');

        abort_unless(
            $part->catalog?->is_active && $part->catalog->manufacturer?->is_active,
            404,
        );

        return view('parts.public-show', [
            'part' => $part,
            'qrCode' => $qrCodeGenerator->svg($part->publicShareUrl()),
        ]);
    }
}
