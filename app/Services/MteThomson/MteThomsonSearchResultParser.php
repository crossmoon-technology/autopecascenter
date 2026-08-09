<?php

namespace App\Services\MteThomson;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Parses cate.mte-thomson.com.br's search results grid (server-rendered
 * ASP.NET MVC, no JS needed). Each match is one `<tr class="grid-row...">`
 * with columns in a fixed order — Foto, Cód. Produto (data-name="PARTNUMBER"),
 * Nome (data-name="NOME_LINHA_PRODUTO"), Aplicação, Posição, Original ou
 * equivalente — confirmed live the last 3 don't carry a `data-name`, so
 * they're located by position relative to the Nome cell instead.
 *
 * Aplicação and "Original ou equivalente" are both `<ul>` lists of `<li>`
 * entries, capped with a trailing "+ (N) aplicações"/"+ (N) OEMs" link when
 * there are more than fit inline — that trailing entry is the only `<li>`
 * wrapping an `<a>`, which is what distinguishes it from a real entry.
 *
 * Results are also paginated (confirmed live: 12/page, ?grid-page=N query
 * param on the same URL) — a page's own pager widget (`.pagination` links,
 * `Total de itens: N` label) only renders at all when there's more than one
 * page; a single-page result has neither, so extractLastPage() defaulting to
 * 1 when no `grid-page=` links are found covers that case naturally.
 */
final class MteThomsonSearchResultParser
{
    public static function extractTotal(string $html): ?int
    {
        return preg_match('/Total de itens:<\/label>\s*<label[^>]*>\s*(\d+)\s*<\/label>/', $html, $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    public static function extractLastPage(string $html): int
    {
        preg_match_all('/[?&]grid-page=(\d+)/', $html, $matches);

        return $matches[1] === [] ? 1 : max(array_map('intval', $matches[1]));
    }

    /**
     * @return array<int, array{codigo: string, descricao: string, imagem_src: ?string, aplicacao: ?string, conversoes: array<int, string>, product_path: ?string}>
     */
    public static function extractResults(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//tr[contains(concat(" ", normalize-space(@class), " "), " grid-row ")]');

        $results = [];

        foreach ($rows as $row) {
            $result = self::parseRow($xpath, $row);

            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    private static function parseRow(DOMXPath $xpath, DOMNode $row): ?array
    {
        $codigoTd = $xpath->query('.//td[@data-name="PARTNUMBER"]', $row)->item(0);
        $nomeTd = $xpath->query('.//td[@data-name="NOME_LINHA_PRODUTO"]', $row)->item(0);

        if ($codigoTd === null || $nomeTd === null) {
            return null;
        }

        $codigo = trim($codigoTd->textContent);

        if ($codigo === '') {
            return null;
        }

        $aplicacaoTd = $xpath->query('following-sibling::td[1]', $nomeTd)->item(0);
        $originalTd = $xpath->query('following-sibling::td[3]', $nomeTd)->item(0);

        $img = $xpath->query('.//img', $row)->item(0);
        $productLink = $xpath->query('.//a[contains(@href, "/produto/detalhes/")]', $row)->item(0);

        return [
            'codigo' => $codigo,
            'descricao' => trim($nomeTd->textContent),
            'imagem_src' => $img instanceof DOMElement ? $img->getAttribute('src') : null,
            'aplicacao' => $aplicacaoTd !== null ? self::summarizeList($xpath, $aplicacaoTd) : null,
            'conversoes' => $originalTd !== null ? self::extractListEntries($xpath, $originalTd) : [],
            'product_path' => $productLink instanceof DOMElement ? trim($productLink->getAttribute('href')) : null,
        ];
    }

    private static function summarizeList(DOMXPath $xpath, DOMNode $cell): ?string
    {
        $entries = self::extractListEntries($xpath, $cell);

        return $entries !== [] ? implode(', ', $entries) : null;
    }

    /**
     * @return array<int, string>
     */
    private static function extractListEntries(DOMXPath $xpath, DOMNode $cell): array
    {
        // Exclui o <li> com um <a> dentro — é sempre o link "+ (N) ..." de "ver mais",
        // nunca uma entrada de verdade (essas são texto puro).
        $items = $xpath->query('.//li[not(.//a)]', $cell);

        $entries = [];

        foreach ($items as $item) {
            $text = trim(preg_replace('/\s+/u', ' ', $item->textContent));

            if ($text !== '') {
                $entries[] = $text;
            }
        }

        return $entries;
    }
}
