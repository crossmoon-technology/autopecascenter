<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ImportCatalogParts;
use App\Jobs\ScrapeCatalog;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Part;
use App\Services\CatalogScraping\Scrapers\C123CatalogScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScrapeCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scrapers' => [
            ['name' => 'Willtec', 'slug' => 'willtec', 'url' => 'https://c123.com.br/willtec', 'class' => C123CatalogScraper::class],
        ]]);
    }

    private function fakeResAsp(int $total, string $sourceVersion, array $products): void
    {
        $literals = collect($products)->map(fn (array $p, int $i) => "mPrd[{$i}]=new fP();with(mPrd[{$i}]){c={$p['c']};n='{$p['n']}';d='{$p['d']}';i='1';t='{$p['t']}';g='{$p['g']}';s='{$p['s']}';p='0';}")->implode('');

        $html = <<<HTML
            <html><body>
            function fP(){this.c=0;this.n='';this.d='';this.i='';this.t='';this.qd='';this.pd='';this.ld='';this.g='';this.s='';this.p=0;}var mPrd=new Array();{$literals}
            var mOB='1',mOBa=1,mOBf='',mTotPrd={$total};
            <DIV id=divUltimaAtualizacao style="POSITION:absolute;">{$sourceVersion}</DIV>
            </body></html>
            HTML;

        Http::fake([
            'c123.com.br/willtec/res.asp' => Http::response(mb_convert_encoding($html, 'ISO-8859-1', 'UTF-8'), 200),
        ]);
    }

    public function test_stores_the_jsonl_file_and_dispatches_the_import_job(): void
    {
        Storage::fake('local');
        Queue::fake([ImportCatalogParts::class]);

        $catalog = Catalog::factory()->create(['file' => null, 'source_version' => null, 'scraper_slug' => 'willtec']);

        $this->fakeResAsp(1, '05-AGO-2026a', [
            ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
        ]);

        ScrapeCatalog::dispatchSync($catalog);

        $catalog->refresh();
        $this->assertNotNull($catalog->file);
        $this->assertSame('05-AGO-2026a', $catalog->source_version);
        $this->assertSame(ImportStatus::Importing, $catalog->import_status);

        $lines = array_filter(explode("\n", Storage::disk('local')->get($catalog->file)));
        $decoded = json_decode(array_values($lines)[0], true);
        $this->assertSame('W01', $decoded['codigo']);

        Queue::assertPushed(ImportCatalogParts::class, fn ($job) => $job->catalog->is($catalog));
    }

    /**
     * "Extraído em" era só um campo manual (preenchido pelo admin ao subir um
     * arquivo) — pra catálogos com scraper, o próprio job precisa registrar
     * quando os dados foram de fato extraídos da fonte, já que não há upload
     * manual nenhum acontecendo.
     */
    public function test_sets_extracted_at_to_now_when_the_source_changed(): void
    {
        Storage::fake('local');
        Queue::fake([ImportCatalogParts::class]);

        $catalog = Catalog::factory()->create([
            'file' => null,
            'source_version' => null,
            'extracted_at' => null,
            'scraper_slug' => 'willtec',
        ]);

        $this->fakeResAsp(1, '05-AGO-2026a', [
            ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
        ]);

        ScrapeCatalog::dispatchSync($catalog);

        $this->assertTrue($catalog->refresh()->extracted_at->isToday());
    }

    /**
     * Um scrape que não achou mudança nenhuma não "reextraiu" nada de novo —
     * a data registrada deve continuar refletindo a última vez que os dados
     * realmente vieram da fonte, não a última vez que só conferimos.
     */
    public function test_does_not_touch_extracted_at_when_the_source_version_is_unchanged(): void
    {
        Storage::fake('local');
        Queue::fake([ImportCatalogParts::class]);

        $catalog = Catalog::factory()->create([
            'file' => 'catalogs/current.jsonl',
            'source_version' => '05-AGO-2026a',
            'extracted_at' => '2026-07-01',
            'scraper_slug' => 'willtec',
        ]);

        $this->fakeResAsp(1, '05-AGO-2026a', [
            ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
        ]);

        ScrapeCatalog::dispatchSync($catalog);

        $this->assertSame('2026-07-01', $catalog->refresh()->extracted_at->toDateString());
    }

    public function test_replaces_the_previous_file_when_the_source_changed(): void
    {
        Storage::fake('local');
        Queue::fake([ImportCatalogParts::class]);

        Storage::disk('local')->put('catalogs/old.jsonl', json_encode(['codigo' => 'OLD']));
        $catalog = Catalog::factory()->create([
            'file' => 'catalogs/old.jsonl',
            'source_version' => '24-JUL-2026b',
            'scraper_slug' => 'willtec',
        ]);

        $this->fakeResAsp(1, '05-AGO-2026a', [
            ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
        ]);

        ScrapeCatalog::dispatchSync($catalog);

        $catalog->refresh();
        $this->assertNotSame('catalogs/old.jsonl', $catalog->file);
        Storage::disk('local')->assertMissing('catalogs/old.jsonl');
    }

    public function test_does_nothing_when_the_source_version_is_unchanged(): void
    {
        Storage::fake('local');
        Queue::fake([ImportCatalogParts::class]);

        $catalog = Catalog::factory()->create([
            'file' => 'catalogs/current.jsonl',
            'source_version' => '05-AGO-2026a',
            'scraper_slug' => 'willtec',
        ]);

        $this->fakeResAsp(1, '05-AGO-2026a', [
            ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
        ]);

        ScrapeCatalog::dispatchSync($catalog);

        $catalog->refresh();
        $this->assertSame('catalogs/current.jsonl', $catalog->file);
        Queue::assertNotPushed(ImportCatalogParts::class);
    }

    /**
     * Reproduz o bug relatado: o botão "Rodar scraper" força import_status pra
     * Importing antes de despachar o job (pra travar o botão enquanto roda) —
     * se a fonte não mudou, o job precisa devolver o status real, senão o
     * catálogo fica com "Importing" preso pra sempre e o botão nunca reaparece.
     */
    public function test_resolves_import_status_back_to_imported_when_unchanged_and_already_has_parts(): void
    {
        Storage::fake('local');
        Queue::fake([ImportCatalogParts::class]);

        $catalog = Catalog::factory()->create([
            'file' => 'catalogs/current.jsonl',
            'source_version' => '05-AGO-2026a',
            'scraper_slug' => 'willtec',
            'import_status' => ImportStatus::Importing,
        ]);
        Part::factory()->create(['catalog_id' => $catalog->id]);

        $this->fakeResAsp(1, '05-AGO-2026a', [
            ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
        ]);

        ScrapeCatalog::dispatchSync($catalog);

        $this->assertSame(ImportStatus::Imported, $catalog->refresh()->import_status);
    }

    public function test_resolves_import_status_back_to_not_imported_when_unchanged_and_never_had_parts(): void
    {
        Storage::fake('local');
        Queue::fake([ImportCatalogParts::class]);

        $catalog = Catalog::factory()->create([
            'file' => 'catalogs/current.jsonl',
            'source_version' => '05-AGO-2026a',
            'scraper_slug' => 'willtec',
            'import_status' => ImportStatus::Importing,
        ]);

        $this->fakeResAsp(1, '05-AGO-2026a', [
            ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
        ]);

        ScrapeCatalog::dispatchSync($catalog);

        $this->assertSame(ImportStatus::NotImported, $catalog->refresh()->import_status);
    }

    public function test_resolves_import_status_back_when_the_scrape_itself_fails(): void
    {
        Queue::fake([ImportCatalogParts::class]);

        $catalog = Catalog::factory()->create([
            'source_version' => null,
            'scraper_slug' => 'willtec',
            'import_status' => ImportStatus::Importing,
        ]);
        Part::factory()->create(['catalog_id' => $catalog->id]);

        Http::fake([
            'c123.com.br/willtec/res.asp' => Http::response('', 500),
        ]);

        try {
            ScrapeCatalog::dispatchSync($catalog);
            $this->fail('Expected the request failure to propagate.');
        } catch (RequestException) {
            // esperado — o job deve deixar a exceção subir pra fila registrar a falha
        }

        $this->assertSame(ImportStatus::Imported, $catalog->refresh()->import_status);
    }

    public function test_does_nothing_when_the_catalog_has_no_scraper_slug(): void
    {
        Queue::fake([ImportCatalogParts::class]);
        $catalog = Catalog::factory()->create(['scraper_slug' => null]);

        ScrapeCatalog::dispatchSync($catalog);

        Http::assertNothingSent();
        Queue::assertNotPushed(ImportCatalogParts::class);
    }

    /**
     * Reproduz um bug real: a "Rodar scraper" trava import_status em Importing
     * antes de despachar o job, mas se o registry não resolver nenhum scraper
     * pro scraper_slug configurado (ex: um worker com processo/opcache ainda
     * desatualizado em relação a um scraper recém-adicionado em config/scrapers.php),
     * o handle() só dava "return" sem nunca resolver o status de volta — o
     * catálogo ficava preso em Importing pra sempre, com o botão escondido e
     * nenhum job/erro em lugar nenhum pra apontar a causa.
     */
    public function test_resolves_import_status_back_when_no_scraper_resolves_for_the_configured_slug(): void
    {
        Queue::fake([ImportCatalogParts::class]);

        $catalog = Catalog::factory()->create([
            'scraper_slug' => 'nao-configurado',
            'import_status' => ImportStatus::Importing,
        ]);
        Part::factory()->create(['catalog_id' => $catalog->id]);

        ScrapeCatalog::dispatchSync($catalog);

        Http::assertNothingSent();
        Queue::assertNotPushed(ImportCatalogParts::class);
        $this->assertSame(ImportStatus::Imported, $catalog->refresh()->import_status);
    }

    /**
     * Reproduz um caso real: um scrape do Willtec estourou o timeout do worker
     * (60s padrão, sem --timeout configurado) e o processo do job foi morto de
     * fora — nesse caso o try/catch dentro de handle() nunca roda, só o hook
     * failed() do Laravel (chamado quando o job é considerado definitivamente
     * falho, inclusive por timeout). Sem isso, o catálogo ficava preso em
     * Importing pra sempre.
     */
    public function test_failed_hook_resolves_import_status_back_to_imported(): void
    {
        $catalog = Catalog::factory()->create([
            'scraper_slug' => 'willtec',
            'import_status' => ImportStatus::Importing,
        ]);
        Part::factory()->create(['catalog_id' => $catalog->id]);

        (new ScrapeCatalog($catalog))->failed(null);

        $this->assertSame(ImportStatus::Imported, $catalog->refresh()->import_status);
    }

    public function test_failed_hook_resolves_import_status_back_to_not_imported(): void
    {
        $catalog = Catalog::factory()->create([
            'scraper_slug' => 'willtec',
            'import_status' => ImportStatus::Importing,
        ]);

        (new ScrapeCatalog($catalog))->failed(null);

        $this->assertSame(ImportStatus::NotImported, $catalog->refresh()->import_status);
    }
}
