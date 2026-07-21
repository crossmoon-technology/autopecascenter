<?php

namespace App\Services\QrCode;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeGenerator
{
    /**
     * Gera o QR code inteiramente no servidor (sem chamar nenhuma API de terceiro),
     * já que o conteúdo é sempre uma URL nossa — não faz sentido vazar isso pra fora.
     */
    public function svg(string $data, int $size = 220): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($data);
    }
}
