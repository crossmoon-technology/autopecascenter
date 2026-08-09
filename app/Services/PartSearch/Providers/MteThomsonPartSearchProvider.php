<?php

namespace App\Services\PartSearch\Providers;

use App\Services\MteThomson\MteThomsonSearchResultParser;
use App\Services\PartSearch\PartSearchProvider;
use App\Services\PartSearch\PartSearchResult;
use App\Services\PartSearch\PartSearchResultPage;
use Illuminate\Support\Facades\Http;

/**
 * MTE-Thomson's catalog (cate.mte-thomson.com.br) has no bulk "list
 * everything" mode (see the ~7h/4625-product estimate that made a full
 * CatalogScraping scraper impractical) and no scrapable listing endpoint, so
 * this queries the site live per search instead.
 *
 * Submitting the search FORM (POST /produto/pesquisarporcodpost) turned out
 * flaky in testing — it worked once, then started 302-ing to a "PageNotFound"
 * page on repeat attempts even from a brand new session — but a direct GET to
 * the exact same redirect target it produces
 * (/pt/br/produto/pesquisar/{codigo}-{tipo}/{exata}/lp-todas) works reliably
 * and needs no session/cookie at all, confirmed live across several different
 * codes. `{tipo}=1` searches MTE-Thomson's own code (not OEM/barcode — see the
 * TipoCodPesquisa radio on the site's own form), `{exata}=false` allows
 * partial/substring matches.
 *
 * Confirmed live in production: a real search hit a plain connection timeout
 * (cURL error 28, 0 bytes received) with no retry at all — unlike every bulk
 * CatalogScraping scraper, this had none, so a single one-off blip fully
 * failed the search. Since this runs synchronously while a seller waits (not
 * a background job), the retry stays short (2 attempts, 300ms) — enough to
 * ride out a blip without making a real outage feel like a long hang.
 *
 * Results are paginated by the site itself (confirmed live: 12/page) via a
 * `grid-page` query param appended to that same URL — a broad term easily
 * returns 100+ matches, so this was silently truncating to whatever fit on
 * page 1 until pagination was wired through to the UI.
 */
class MteThomsonPartSearchProvider implements PartSearchProvider
{
    private const string BASE_URL = 'https://cate.mte-thomson.com.br';

    public function search(string $query, int $page = 1): PartSearchResultPage
    {
        $response = Http::timeout(15)
            ->retry(2, 300)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->get(self::BASE_URL.'/pt/br/produto/pesquisar/'.rawurlencode($query).'-1/false/lp-todas', ['grid-page' => $page])
            ->throw();

        $body = $response->body();

        $results = collect(MteThomsonSearchResultParser::extractResults($body))
            ->map(fn (array $result) => new PartSearchResult(
                codigo: $result['codigo'],
                descricao: $result['descricao'],
                imagem_url: $result['imagem_src'],
                aplicacao: $result['aplicacao'],
                conversoes: $result['conversoes'],
                product_url: $result['product_path'] !== null ? self::BASE_URL.$result['product_path'] : null,
            ))
            ->values();

        return new PartSearchResultPage(
            results: $results,
            currentPage: $page,
            lastPage: MteThomsonSearchResultParser::extractLastPage($body),
            total: MteThomsonSearchResultParser::extractTotal($body),
        );
    }
}
