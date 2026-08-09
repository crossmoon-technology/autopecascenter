<?php

namespace App\Services\Rpd;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * HTML-table parser for rpdborrachas.com.br's server-rendered catalog pages,
 * used by the full-catalog bulk scraper
 * (Services\CatalogScraping\Scrapers\RpdCatalogScraper) — an empty `pesquisa`
 * param returns the whole catalog instead of nothing.
 *
 * Each part is one <table class="listapecas"> preceded by a <div class="cabeitem">
 * header carrying "MONTADORA - GRUPO". The "lpecanum" cell holds the primary
 * codigo as a bare text node, optionally followed by a <span> with one or more
 * <br>-separated entries — confirmed live these entries are usually genuine
 * cross-reference/OEM codes (e.g. "95.463.563"), but sometimes just an
 * annotation with no code at all (e.g. "(1 LADO)" on a kit sold per side) — so
 * each entry is stripped of parenthetical text and anything left over must
 * still contain a digit and have a minimum length to count as a real
 * conversão, exactly like the same false-positive risk found on Kaer's
 * free-text "CÓDIGOS:" section.
 */
final class RpdProductParser
{
    /**
     * @return array<int, array{codigo: string, conversoes: ?array<int, string>, descricao: string, montadora: ?string, grupo: ?string, aplicacao: ?string, imagem_src: ?string}>
     */
    public static function extractProducts(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $tables = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " listapecas ")]');

        $products = [];

        foreach ($tables as $table) {
            $product = self::parseTable($xpath, $table);

            if ($product !== null) {
                $products[] = $product;
            }
        }

        return $products;
    }

    private static function parseTable(DOMXPath $xpath, DOMNode $table): ?array
    {
        $numTd = $xpath->query('.//td[contains(concat(" ", normalize-space(@class), " "), " lpecanum ")]', $table)->item(0);

        if ($numTd === null) {
            return null;
        }

        [$codigo, $conversoes] = self::parseCodigoCell($numTd);

        if ($codigo === '') {
            return null;
        }

        $img = $xpath->query('.//td[contains(concat(" ", normalize-space(@class), " "), " lpecaimg ")]//img', $table)->item(0);
        $descricaoTd = $xpath->query('.//td[contains(concat(" ", normalize-space(@class), " "), " lpecadescricao ")]', $table)->item(0);
        $aplicacaoTd = $xpath->query('.//td[contains(concat(" ", normalize-space(@class), " "), " lpecaaplicaco ")]', $table)->item(0);
        $anoTd = $xpath->query('.//td[contains(concat(" ", normalize-space(@class), " "), " lpecaano ")]', $table)->item(0);

        $header = $xpath->query('preceding-sibling::div[contains(concat(" ", normalize-space(@class), " "), " cabeitem ")][1]', $table)->item(0);
        [$montadora, $grupo] = self::parseHeader($header);

        $aplicacoes = $aplicacaoTd ? self::splitByBr($aplicacaoTd) : [];
        $anos = $anoTd ? self::splitByBr($anoTd) : [];

        return [
            'codigo' => $codigo,
            'conversoes' => $conversoes,
            'descricao' => $descricaoTd ? trim($descricaoTd->textContent) : $codigo,
            'montadora' => $montadora,
            'grupo' => $grupo,
            'aplicacao' => self::summarizeAplicacoes($aplicacoes, $anos),
            'imagem_src' => $img?->getAttribute('src') ?: null,
        ];
    }

    /**
     * @return array{0: string, 1: ?array<int, string>}
     */
    private static function parseCodigoCell(DOMNode $numTd): array
    {
        $p = null;

        foreach ($numTd->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'p') {
                $p = $child;

                break;
            }
        }

        if ($p === null) {
            return ['', null];
        }

        $codigo = '';
        $span = null;

        foreach ($p->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'span') {
                $span = $child;
            } elseif ($span === null && strtolower($child->nodeName) !== 'br') {
                $codigo .= $child->textContent;
            }
        }

        $codigo = trim($codigo);

        if ($codigo === '' || $span === null) {
            return [$codigo, null];
        }

        $normalizedCodigo = self::stripToAlnum($codigo);

        $conversoes = collect(self::splitByBr($span))
            ->map(fn (string $entry): string => self::stripToAlnum((string) preg_replace('/\([^)]*\)/', ' ', $entry)))
            ->filter(fn (string $token): bool => strlen($token) >= 4 && preg_match('/\d/', $token) === 1)
            ->reject(fn (string $token): bool => $token === $normalizedCodigo)
            ->unique()
            ->values();

        return [$codigo, $conversoes->isNotEmpty() ? $conversoes->all() : null];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function parseHeader(?DOMNode $header): array
    {
        if ($header === null) {
            return [null, null];
        }

        $parts = explode(' - ', trim($header->textContent), 2);

        return [
            trim($parts[0]) !== '' ? trim($parts[0]) : null,
            isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function splitByBr(DOMNode $node): array
    {
        $segments = [];
        $current = '';

        foreach ($node->childNodes as $child) {
            if (strtolower($child->nodeName) === 'br') {
                $segments[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $child->textContent;
        }

        $segments[] = trim($current);

        return $segments;
    }

    /**
     * The "ano" column only lines up 1-to-1 with "aplicação" when both have the
     * same number of <br>-separated entries — some parts carry a single "todos"
     * value covering every listed model instead, so years are only appended when
     * the counts actually match.
     *
     * @param  array<int, string>  $aplicacoes
     * @param  array<int, string>  $anos
     */
    private static function summarizeAplicacoes(array $aplicacoes, array $anos): ?string
    {
        $hasMatchingAnos = $anos !== [] && count($anos) === count($aplicacoes);

        $parts = [];

        foreach ($aplicacoes as $i => $modelo) {
            if ($modelo === '') {
                continue;
            }

            $parts[] = $hasMatchingAnos && $anos[$i] !== ''
                ? "{$modelo} ({$anos[$i]})"
                : $modelo;
        }

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    private static function stripToAlnum(string $value): string
    {
        return strtoupper((string) preg_replace('/[^a-zA-Z0-9]/u', '', $value));
    }
}
