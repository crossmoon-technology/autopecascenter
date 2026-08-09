<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\ZM\ZmProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes ZM S.A.'s catalog (extranet.zm.com.br) — a starter motor/
 * alternator/fastener manufacturer. Despite the "extranet" hostname this is
 * the SAME public, unauthenticated JSON API that ZM's own public "Catálogo
 * On-Line" page (www.zm.com.br/catalogo, an AngularJS SPA) calls itself —
 * reverse-engineered from its shipped JS (catalogoService.js/
 * catalogoCtrl3.js/app.js), not any private/credentialed endpoint. Its one
 * quirk: every request needs a `token-server` field carrying a token that's
 * hardcoded, in plaintext, inside that same public JS bundle
 * (`TOKEN_SERVER` in static.zm.com.br/js/angular/config.js) — not a secret,
 * just this API's way of gating requests to its own frontend; if ZM ever
 * rotates it, every request here fails with a clear "Token server not
 * informed"-style error rather than silently returning nothing, so a stale
 * token is easy to spot.
 *
 * Unlike every HTML-scraped source elsewhere in this app, this is a clean
 * JSON API returning EVERY product for a given `cod_tipo_produto` (product
 * type) in a single POST — confirmed live up to 2683 products in one
 * response — so there's no per-product detail fetch and no pagination to
 * manage: just enumerate the product types via `catalogo-tipos-produto`
 * (fetched live each run, not hardcoded, in case ZM adds/removes a type —
 * confirmed live: 39 types) and issue one `catalogo-busca-produtos` POST per
 * type (see ZmProductParser for why that response's field layout has to be
 * read per-type rather than assumed fixed).
 *
 * `ind_grupo=A`/`ind_brasil=N` (confirmed live against the site's own
 * catalogoCtrl3.js: 'A' is the default, unfiltered "Elétrico/Mecânico"
 * view combining both product segments; 'N' means not restricted to
 * Brazil-only applications) — the broadest, most complete slice available.
 *
 * No cheap "did it change" marker exists here (same tradeoff as Kaer/RPD/
 * Fania/MG Freios): always fetch everything, then fingerprint the sorted
 * codigo list to decide whether to bother storing/importing.
 */
class ZmCatalogScraper implements CatalogScraper
{
    private const string USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    /**
     * Publicly embedded in ZM's own site JS (static.zm.com.br/js/angular/
     * config.js, `TOKEN_SERVER` constant) — confirmed live, not a credential
     * obtained out-of-band. See class docblock.
     */
    private const string TOKEN_SERVER = 'x4654D55s4sazx4584sjiw84Usi';

    private const int TYPE_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        $tipos = $this->fetchTiposProduto();

        if ($tipos === []) {
            throw new RuntimeException(sprintf(
                'ZmCatalogScraper (%s): não foi possível obter a lista de tipos de produto — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.',
                $this->baseUrl
            ));
        }

        $products = [];

        foreach ($tipos as $index => $codTipoProduto) {
            if ($index > 0) {
                usleep(self::TYPE_REQUEST_DELAY_MICROSECONDS);
            }

            $products = [...$products, ...$this->fetchProdutos($codTipoProduto)];
        }

        $source_version = $this->fingerprint($products);

        if ($source_version === $known_source_version) {
            return null;
        }

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array<int, string>
     */
    private function fetchTiposProduto(): array
    {
        $response = Http::timeout(20)
            ->retry(5, 2000)
            ->withUserAgent(self::USER_AGENT)
            ->get($this->baseUrl.'/api/catalogo-tipos-produto/idioma/1/ind_grupo/A/token-server/'.self::TOKEN_SERVER)
            ->throw();

        return collect($response->json('message', []))
            ->pluck('cod_tipo_produto')
            ->filter(fn ($cod) => filled($cod))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{codigo: string, descricao: string, conversoes: ?array<int, string>, fabricante: ?string, grupo: ?string, imagem_url: ?string}>
     */
    private function fetchProdutos(string $codTipoProduto): array
    {
        $response = Http::timeout(30)
            ->retry(5, 2000)
            ->withUserAgent(self::USER_AGENT)
            ->asJson()
            ->post($this->baseUrl.'/api/catalogo-busca-produtos', [
                'token-server' => self::TOKEN_SERVER,
                'idioma' => 1,
                'ind_grupo' => 'A',
                'ind_brasil' => 'N',
                'cod_tipo_produto' => $codTipoProduto,
            ])
            ->throw();

        $tipoResponse = $response->json('message.0');

        return is_array($tipoResponse) ? ZmProductParser::extractProducts($tipoResponse) : [];
    }

    /**
     * @param  array<int, array{codigo: string}>  $products
     */
    private function fingerprint(array $products): string
    {
        $codigos = collect($products)->pluck('codigo')->sort()->implode('|');

        return hash('sha256', $codigos);
    }
}
