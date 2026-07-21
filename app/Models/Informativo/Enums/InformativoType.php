<?php

namespace App\Models\Informativo\Enums;

enum InformativoType: string
{
    case Pdf = 'pdf';
    case Imagem = 'imagem';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Imagem => 'Imagem',
        };
    }

    public static function fromExtension(string $extension): self
    {
        return strtolower($extension) === 'pdf' ? self::Pdf : self::Imagem;
    }
}
