<?php

namespace Tests\Feature\Services\QuotationExport;

use App\Models\Manufacturer;
use App\Models\Quotation\Enums\Status;
use App\Models\QuotationItem\Enums\Source;
use App\Models\User;
use App\Services\QuotationExport\QuotationExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Tests\TestCase;

class QuotationExporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_pdf_generates_a_valid_pdf_document(): void
    {
        $quotation = $this->quotationWithOneItem();

        $pdf = app(QuotationExporter::class)->toPdf($quotation);

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_to_pdf_still_works_when_the_user_has_no_logo(): void
    {
        $quotation = $this->quotationWithOneItem();
        $quotation->user->update(['logo' => null]);

        $pdf = app(QuotationExporter::class)->toPdf($quotation);

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_to_pdf_still_works_when_the_logo_path_points_to_a_missing_file(): void
    {
        $quotation = $this->quotationWithOneItem();
        $quotation->user->update(['logo' => 'users/logos/does-not-exist.png']);

        $pdf = app(QuotationExporter::class)->toPdf($quotation);

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_to_pdf_embeds_the_users_logo_when_set(): void
    {
        Storage::fake('public');
        $pngContents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('public')->put('users/logos/logo.png', $pngContents);

        $quotation = $this->quotationWithOneItem();
        $quotation->user->update(['logo' => 'users/logos/logo.png']);

        $pdf = app(QuotationExporter::class)->toPdf($quotation);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('XObject', $pdf);
    }

    public function test_to_csv_includes_a_utf8_bom_header_row_and_the_item(): void
    {
        $quotation = $this->quotationWithOneItem();

        $csv = app(QuotationExporter::class)->toCsv($quotation);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Fabricante,Código,Quantidade,Nota', $csv);
        $this->assertStringContainsString('Cofap,HF-21,4,"Cliente pediu urgência"', $csv);
    }

    public function test_to_json_encodes_fabricante_codigo_quantidade_and_nota(): void
    {
        $quotation = $this->quotationWithOneItem();

        $json = app(QuotationExporter::class)->toJson($quotation);
        $decoded = json_decode($json, true);

        $this->assertCount(1, $decoded);
        $this->assertSame(['Fabricante', 'Código', 'Quantidade', 'Nota'], array_keys($decoded[0]));
        $this->assertSame('Cofap', $decoded[0]['Fabricante']);
        $this->assertSame('HF-21', $decoded[0]['Código']);
        $this->assertSame('4', $decoded[0]['Quantidade']);
        $this->assertSame('Cliente pediu urgência', $decoded[0]['Nota']);
    }

    public function test_to_xlsx_generates_a_readable_spreadsheet_with_the_item(): void
    {
        $quotation = $this->quotationWithOneItem();

        $binary = app(QuotationExporter::class)->toXlsx($quotation);

        $tmpFile = tempnam(sys_get_temp_dir(), 'quotation-export-test-').'.xlsx';
        file_put_contents($tmpFile, $binary);

        $sheet = (new Xlsx)->load($tmpFile)->getActiveSheet();

        $this->assertSame('Fabricante', $sheet->getCell('A1')->getValue());
        $this->assertSame('Cofap', $sheet->getCell('A2')->getValue());
        $this->assertSame('HF-21', $sheet->getCell('B2')->getValue());
        $this->assertEquals(4, $sheet->getCell('C2')->getValue());

        unlink($tmpFile);
    }

    private function quotationWithOneItem()
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $user = User::factory()->create();
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $quotation->items()->create([
            'manufacturer_id' => $manufacturer->id,
            'source' => Source::Iframe,
            'codigo' => 'HF-21',
            'quantity' => 4,
            'descricao' => 'MOLA A GÁS',
            'note' => 'Cliente pediu urgência',
        ]);

        return $quotation;
    }
}
