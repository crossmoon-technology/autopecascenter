<?php

namespace App\Services\QuotationExport;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class QuotationExporter
{
    /**
     * @var array<int, string>
     */
    private const array COLUMNS = ['Fabricante', 'Código', 'Nota'];

    public function toPdf(Quotation $quotation): string
    {
        return Pdf::loadView('pdf.quotation', [
            'quotation' => $quotation,
            'rows' => $this->rows($quotation),
            'userLogo' => $this->userLogo($quotation->user),
        ])->output();
    }

    /**
     * BOM no início pra o Excel abrir os acentos certos — sem isso ele assume Latin-1 e
     * "Código"/"Descrição" viram caracteres quebrados.
     */
    public function toCsv(Quotation $quotation): string
    {
        $stream = fopen('php://temp', 'r+');

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::COLUMNS);

        foreach ($this->rows($quotation) as $row) {
            fputcsv($stream, array_map(fn (?string $value): string => $value ?? '', array_values($row)));
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    public function toXlsx(Quotation $quotation): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cotação');

        $sheet->fromArray(self::COLUMNS, null, 'A1');

        $rows = $this->rows($quotation)
            ->map(fn (array $row): array => array_map(fn (?string $value): string => $value ?? '', array_values($row)))
            ->all();

        $sheet->fromArray($rows, null, 'A2');

        foreach (range('A', 'C') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');

        return ob_get_clean();
    }

    public function toJson(Quotation $quotation): string
    {
        return $this->rows($quotation)->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Embutida como base64 em vez de referenciada por URL — o dompdf renderiza fora de um
     * navegador, sem sessão/cookies, então uma URL apontando pro próprio painel exigiria
     * habilitar requisições remotas nele; embutir o arquivo já lido do disco evita essa
     * dependência de rede por completo.
     *
     * width/height calculados aqui e não só via CSS porque o dompdf simplesmente não
     * desenha a imagem (sem erro nenhum) quando não consegue resolver uma largura em
     * pixel concreta pra ela — só max-width/height no CSS não é suficiente.
     *
     * @return array{uri: string, width: int, height: int}|null
     */
    private function userLogo(User $user): ?array
    {
        if (blank($user->logo) || (! Storage::disk('public')->exists($user->logo))) {
            return null;
        }

        $contents = Storage::disk('public')->get($user->logo);
        $mimeType = Storage::disk('public')->mimeType($user->logo);

        $targetHeight = 36;
        $maxWidth = 140;
        $width = $targetHeight;
        $height = $targetHeight;

        $imageSize = @getimagesizefromstring($contents);

        if ($imageSize && $imageSize[1] > 0) {
            $width = (int) round($imageSize[0] * ($targetHeight / $imageSize[1]));

            if ($width > $maxWidth) {
                $height = (int) round($targetHeight * ($maxWidth / $width));
                $width = $maxWidth;
            }
        }

        return [
            'uri' => "data:{$mimeType};base64,".base64_encode($contents),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @return Collection<int, array<string, string|null>>
     */
    private function rows(Quotation $quotation): Collection
    {
        return $quotation->items
            ->loadMissing('manufacturer')
            ->map(fn (QuotationItem $item): array => [
                'Fabricante' => $item->manufacturer?->name,
                'Código' => $item->codigo,
                'Nota' => $item->note,
            ]);
    }
}
