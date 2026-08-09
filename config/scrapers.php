<?php

use App\Services\CatalogScraping\Scrapers\AteCatalogScraper;
use App\Services\CatalogScraping\Scrapers\Basso3bCatalogScraper;
use App\Services\CatalogScraping\Scrapers\BiagioTurbosCatalogScraper;
use App\Services\CatalogScraping\Scrapers\BuscaNaRedeCatalogScraper;
use App\Services\CatalogScraping\Scrapers\C123CatalogScraper;
use App\Services\CatalogScraping\Scrapers\FaniaCatalogScraper;
use App\Services\CatalogScraping\Scrapers\FersaCatalogScraper;
use App\Services\CatalogScraping\Scrapers\HellaCatalogScraper;
use App\Services\CatalogScraping\Scrapers\IksCatalogScraper;
use App\Services\CatalogScraping\Scrapers\KaerCatalogScraper;
use App\Services\CatalogScraping\Scrapers\LionPolimersCatalogScraper;
use App\Services\CatalogScraping\Scrapers\MgFreiosCatalogScraper;
use App\Services\CatalogScraping\Scrapers\MgPecasAutomotivasCatalogScraper;
use App\Services\CatalogScraping\Scrapers\MidePartsCatalogScraper;
use App\Services\CatalogScraping\Scrapers\MsMotorserviceCatalogScraper;
use App\Services\CatalogScraping\Scrapers\NatIndustriaCatalogScraper;
use App\Services\CatalogScraping\Scrapers\OriginalFilterCatalogScraper;
use App\Services\CatalogScraping\Scrapers\RoltensCatalogScraper;
use App\Services\CatalogScraping\Scrapers\RpdCatalogScraper;
use App\Services\CatalogScraping\Scrapers\UfiCatalogScraper;
use App\Services\CatalogScraping\Scrapers\ZmCatalogScraper;

// Provedores de scraping disponíveis para importação automática de catálogos
// (ver App\Console\Commands\ScrapeCatalogs e App\Services\CatalogScraping).
// Cada entrada aqui vira uma opção selecionável no formulário de Catálogo — o
// slug escolhido é salvo em catalogs.scraper_slug.
//
// `class` deve implementar App\Services\CatalogScraping\CatalogScraper e ter um
// construtor `__construct(string $baseUrl)` — nada garante que todo scraper HTML
// terá a mesma estrutura de página, então cada fabricante/plataforma pode (e
// deve, quando a estrutura for diferente) ter sua própria classe de extração em
// vez de forçar tudo a reaproveitar C123CatalogScraper.
return [
    [
        'name' => 'Anroi',
        'slug' => 'anroi',
        'url' => 'https://c123.com.br/anroi',
        'class' => C123CatalogScraper::class,
    ],
    [
        'name' => 'Willtec',
        'slug' => 'willtec',
        'url' => 'https://c123.com.br/willtec',
        'class' => C123CatalogScraper::class,
    ],
    [
        'name' => 'Bel-Ar',
        'slug' => 'bel-ar',
        'url' => 'https://c123.com.br/bel-ar',
        'class' => C123CatalogScraper::class,
    ],
    [
        'name' => 'Kaer',
        'slug' => 'kaer',
        'url' => 'https://www.kaerbrasil.com',
        'class' => KaerCatalogScraper::class,
    ],
    [
        'name' => 'RPD',
        'slug' => 'rpd',
        'url' => 'https://www.rpdborrachas.com.br',
        'class' => RpdCatalogScraper::class,
    ],
    [
        'name' => 'MS Motorservice',
        'slug' => 'ms-motorservice',
        'url' => 'https://catweb.ms-motorservice.com.br',
        'class' => MsMotorserviceCatalogScraper::class,
    ],
    [
        'name' => 'Hella',
        'slug' => 'hella',
        'url' => 'https://catalogoexpresso.com.br/hella',
        'class' => HellaCatalogScraper::class,
    ],
    [
        'name' => 'Fania',
        'slug' => 'fania',
        'url' => 'https://c123.com.br/fania',
        'class' => FaniaCatalogScraper::class,
    ],
    [
        'name' => 'Mide Parts',
        'slug' => 'mideparts',
        'url' => 'https://catalogoexpresso.com.br/mideparts',
        'class' => MidePartsCatalogScraper::class,
    ],
    [
        'name' => 'UFI',
        'slug' => 'ufi',
        'url' => 'https://br.ufi-aftermarket.com',
        'class' => UfiCatalogScraper::class,
    ],
    [
        'name' => 'ATE',
        'slug' => 'ate',
        'url' => 'https://catalogoexpresso.com.br/ATE',
        'class' => AteCatalogScraper::class,
    ],
    [
        'name' => 'Cipec',
        'slug' => 'cipec',
        'url' => 'https://c123.com.br/cipec',
        'class' => C123CatalogScraper::class,
    ],
    [
        'name' => 'Taranto',
        'slug' => 'taranto',
        'url' => 'https://c123.com.br/taranto',
        'class' => C123CatalogScraper::class,
    ],
    [
        'name' => 'Apex',
        'slug' => 'apex',
        'url' => 'https://c123.com.br/apex',
        'class' => C123CatalogScraper::class,
    ],
    [
        'name' => 'MG Freios',
        'slug' => 'mg-freios',
        'url' => 'https://loja.mgfreios.com.br',
        'class' => MgFreiosCatalogScraper::class,
    ],
    [
        'name' => 'ZM',
        'slug' => 'zm',
        'url' => 'https://extranet.zm.com.br',
        'class' => ZmCatalogScraper::class,
    ],
    [
        'name' => 'IKS',
        'slug' => 'iks',
        'url' => 'https://iks.com.br',
        'class' => IksCatalogScraper::class,
    ],
    [
        'name' => 'Roltens',
        'slug' => 'roltens',
        'url' => 'https://catalogo.roltens.com.br',
        'class' => RoltensCatalogScraper::class,
    ],
    [
        'name' => 'Fersa',
        'slug' => 'fersa',
        'url' => 'https://brasil.fersa.com',
        'class' => FersaCatalogScraper::class,
    ],
    [
        'name' => 'Bastos',
        'slug' => 'bastos',
        'url' => 'https://c123.com.br/bastos',
        'class' => C123CatalogScraper::class,
    ],
    [
        'name' => 'Aplic Resolit',
        'slug' => 'aplic',
        'url' => 'https://c123.com.br/aplic',
        'class' => C123CatalogScraper::class,
    ],
    [
        'name' => 'MG Peças Automotivas',
        'slug' => 'mg-pecas-automotivas',
        'url' => 'https://mgpecasautomotivas.com.br',
        'class' => MgPecasAutomotivasCatalogScraper::class,
    ],
    [
        'name' => 'NAT',
        'slug' => 'nat',
        'url' => 'https://natindustria.com.br',
        'class' => NatIndustriaCatalogScraper::class,
    ],
    [
        'name' => 'NTI',
        'slug' => 'nti',
        'url' => 'https://c123.com.br/nti',
        'class' => C123CatalogScraper::class,
    ],
    [
        'name' => 'LINMAX',
        'slug' => 'linmax',
        'url' => 'https://buscanarede.com.br/linmaxbrasil',
        'class' => BuscaNaRedeCatalogScraper::class,
    ],
    [
        'name' => 'Original Filter',
        'slug' => 'original-filter',
        'url' => 'https://catalogoexpresso.com.br/original-filter',
        'class' => OriginalFilterCatalogScraper::class,
    ],
    [
        'name' => 'Válvulas 3b',
        'slug' => 'valvulas-3b',
        'url' => 'https://3bcatalogo.basso.com.ar',
        'class' => Basso3bCatalogScraper::class,
    ],
    [
        'name' => 'Biagio Turbos',
        'slug' => 'biagio-turbos',
        'url' => 'https://catalogo.biagioturbos.com.br',
        'class' => BiagioTurbosCatalogScraper::class,
    ],
    [
        'name' => 'Lion Polimers',
        'slug' => 'lion-polimers',
        'url' => 'https://lionpolimers.com',
        'class' => LionPolimersCatalogScraper::class,
    ],
];
