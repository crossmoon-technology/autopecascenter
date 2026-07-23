@extends('layouts.app')

@section('content')
    <main>
        <section class="hero">
            <div class="container hero__inner">
                <div class="hero__content">
                    <h1 class="hero__title">
                        O sistema que conecta sua empresa a <span class="text-accent">todos</span> os
                        <span class="text-accent">catálogos de autopeças</span>.
                    </h1>

                    <p class="hero__description">
                        Unificamos centenas de catálogos de fornecedores em uma única plataforma para você cotar mais
                        rápido, comparar melhor e vender mais.
                    </p>

                    <ul class="hero__checklist">
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                            Acesse milhares de fornecedores em um só lugar
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                            Compare preços e condições em segundos
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                            Decisões mais assertivas com dados reais
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                            Mais agilidade para você e seus clientes
                        </li>
                    </ul>

                    <div class="hero__actions">
                        <a href="#agendar-demonstracao" class="btn btn--solid">
                            Agendar demonstração
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12" />
                                <polyline points="12 5 19 12 12 19" />
                            </svg>
                        </a>
                        <a href="#como-funciona" class="btn btn--play">
                            <span class="btn__play-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                            </span>
                            Ver como funciona
                        </a>
                    </div>
                </div>

                <div class="hero__media">
                    <div class="browser-mock">
                        <div class="browser-mock__bar">
                            <span class="browser-mock__dot browser-mock__dot--red"></span>
                            <span class="browser-mock__dot browser-mock__dot--yellow"></span>
                            <span class="browser-mock__dot browser-mock__dot--green"></span>
                            <span class="browser-mock__tab">Auto Peças Center - O sistema</span>
                        </div>

                        <div class="browser-mock__address">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <line x1="19" y1="12" x2="5" y2="12" />
                                <polyline points="12 19 5 12 12 5" />
                            </svg>
                            <span>https://www.autopecascenter.com.br</span>
                        </div>

                        <div class="browser-mock__body">
                            <aside class="browser-mock__sidebar">
                                <span class="browser-mock__logo">APC</span>
                                <nav>
                                    <span class="browser-mock__nav-item browser-mock__nav-item--active">Dashboard</span>
                                    <span class="browser-mock__nav-item">Cotações</span>
                                    <span class="browser-mock__nav-item">Fornecedores</span>
                                    <span class="browser-mock__nav-item">Peças</span>
                                    <span class="browser-mock__nav-item">Comparações</span>
                                    <span class="browser-mock__nav-item">Histórico</span>
                                    <span class="browser-mock__nav-item">Relatórios</span>
                                    <span class="browser-mock__nav-item">Configurações</span>
                                </nav>
                            </aside>

                            <div class="browser-mock__main">
                                <div class="browser-mock__search">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="11" cy="11" r="8" />
                                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                    </svg>
                                    <span>Buscar por peça, código ou aplicação</span>
                                </div>

                                <div class="browser-mock__stats">
                                    <div class="browser-mock__stat">
                                        <strong>236</strong>
                                        <span>Cotações realizadas</span>
                                    </div>
                                    <div class="browser-mock__stat">
                                        <strong>1.483</strong>
                                        <span>Fornecedores integrados</span>
                                    </div>
                                    <div class="browser-mock__stat">
                                        <strong>98%</strong>
                                        <span>Respostas em até 2 min</span>
                                    </div>
                                    <div class="browser-mock__stat">
                                        <strong>R$ 342,7 mil</strong>
                                        <span>Economia gerada</span>
                                    </div>
                                </div>

                                <div class="browser-mock__table">
                                    <div class="browser-mock__table-head">
                                        <span>Código</span>
                                        <span>Descrição</span>
                                        <span>Aplicação</span>
                                        <span>Melhor preço</span>
                                        <span>Fornecedores</span>
                                        <span>Status</span>
                                    </div>
                                    <div class="browser-mock__table-row">
                                        <span>BD2701</span>
                                        <span>Kit de Freio Dianteiro</span>
                                        <span>Honda Civic 2018</span>
                                        <span>R$ 126,90</span>
                                        <span>8</span>
                                        <span class="is-done">Finalizada</span>
                                    </div>
                                    <div class="browser-mock__table-row">
                                        <span>FAP287</span>
                                        <span>Filtro de Ar</span>
                                        <span>Fiat Argo 2020</span>
                                        <span>R$ 38,50</span>
                                        <span>12</span>
                                        <span class="is-done">Finalizada</span>
                                    </div>
                                    <div class="browser-mock__table-row">
                                        <span>BTA4060</span>
                                        <span>Bateria 60Ah</span>
                                        <span>VW Gol 2019</span>
                                        <span>R$ 268,00</span>
                                        <span>6</span>
                                        <span class="is-done">Finalizada</span>
                                    </div>
                                    <div class="browser-mock__table-row">
                                        <span>AM7012</span>
                                        <span>Amortecedor Traseiro</span>
                                        <span>Chevrolet Onix 2021</span>
                                        <span>R$ 198,60</span>
                                        <span>9</span>
                                        <span class="is-done">Finalizada</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="partners">
            <div class="container">
                <p class="partners__label">Conectamos você aos principais fornecedores do mercado</p>
                <div class="partners__list">
                    <span class="partners__logo"></span>
                    <span class="partners__logo"></span>
                    <span class="partners__logo"></span>
                    <span class="partners__logo"></span>
                    <span class="partners__logo"></span>
                    <span class="partners__logo"></span>
                    <span class="partners__logo"></span>
                    <span class="partners__logo"></span>
                </div>
            </div>
        </section>

        <section class="features">
            <div class="container">
                <h2 class="features__title">Feito para vendedores, pensado para <span class="text-accent">resultados</span>.</h2>
                <p class="features__subtitle">A plataforma ideal para quem precisa cotar rápido e vender mais.</p>

                <div class="features__grid">
                    <div class="features__card">
                        <span class="features__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                        </span>
                        <h3>Agilidade que vende</h3>
                        <p>Cotações completas em segundos com respostas rápidas dos fornecedores.</p>
                    </div>

                    <div class="features__card">
                        <span class="features__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                        </span>
                        <h3>Mais opções</h3>
                        <p>Acesso a centenas de catálogos de marcas e encontre o que precisa.</p>
                    </div>

                    <div class="features__card">
                        <span class="features__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="1" x2="12" y2="23" />
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                            </svg>
                        </span>
                        <h3>Melhores preços</h3>
                        <p>Compare propostas e escolha a melhor condição para o seu cliente.</p>
                    </div>

                    <div class="features__card">
                        <span class="features__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                                <polyline points="17 6 23 6 23 12" />
                            </svg>
                        </span>
                        <h3>Mais lucro</h3>
                        <p>Aumente sua conversão oferecendo as melhores condições do mercado.</p>
                    </div>

                    <div class="features__card">
                        <span class="features__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            </svg>
                        </span>
                        <h3>Confiável e seguro</h3>
                        <p>Dados protegidos e informações sempre atualizadas.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="steps">
            <div class="container">
                <h2 class="steps__title">Como funciona</h2>

                <div class="steps__list">
                    <div class="steps__item">
                        <span class="steps__number">1</span>
                        <span class="steps__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                        </span>
                        <h3>Você busca a peça</h3>
                        <p>Informe o código, nome ou descreva a peça que precisa.</p>
                    </div>

                    <span class="steps__arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12" />
                            <polyline points="12 5 19 12 12 19" />
                        </svg>
                    </span>

                    <div class="steps__item">
                        <span class="steps__number">2</span>
                        <span class="steps__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="18" cy="5" r="3" />
                                <circle cx="6" cy="12" r="3" />
                                <circle cx="18" cy="19" r="3" />
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49" />
                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49" />
                            </svg>
                        </span>
                        <h3>Consultamos todos os catálogos</h3>
                        <p>Nosso sistema pesquisa em centenas de fornecedores integrados.</p>
                    </div>

                    <span class="steps__arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12" />
                            <polyline points="12 5 19 12 12 19" />
                        </svg>
                    </span>

                    <div class="steps__item">
                        <span class="steps__number">3</span>
                        <span class="steps__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                                <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                            </svg>
                        </span>
                        <h3>Receba as melhores propostas</h3>
                        <p>Você recebe preços, prazos e condições para comparar em um só lugar.</p>
                    </div>

                    <span class="steps__arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12" />
                            <polyline points="12 5 19 12 12 19" />
                        </svg>
                    </span>

                    <div class="steps__item">
                        <span class="steps__number">4</span>
                        <span class="steps__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                <line x1="3" y1="6" x2="21" y2="6" />
                                <path d="M16 10a4 4 0 0 1-8 0" />
                            </svg>
                        </span>
                        <h3>Escolha e feche negócio</h3>
                        <p>Escolha a melhor opção e finalize sua compra com segurança.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="final-cta">
            <div class="container final-cta__inner">
                <div class="final-cta__left">
                    <div class="final-cta__content">
                        <h2>Seu tempo é valioso.<br>Deixe a <span class="text-accent">cotação com a gente.</span></h2>
                        <p>
                            Crie sua conta e descubra como o Auto Peças Center pode transformar o seu dia a dia e os
                            resultados da sua empresa.
                        </p>

                        <ul class="final-cta__benefits">
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 6 9 17l-5-5" />
                                </svg>
                                Mais produtividade para sua equipe
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 6 9 17l-5-5" />
                                </svg>
                                Mais satisfação para seus clientes
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 6 9 17l-5-5" />
                                </svg>
                                Mais resultados para seu negócio
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="final-cta__card">
                    <h3>Quer ver na prática?</h3>
                    <p>Agende uma demonstração gratuita e conheça todo o potencial do sistema.</p>
                    <a href="#agendar-demonstracao" class="btn btn--solid btn--block">Agendar demonstração</a>
                    <p class="final-cta__or">ou fale com nosso time</p>
                    <a href="tel:+5511999999999" class="final-cta__phone">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.850.573 2.81.7A2 2 0 0 1 22 16.92z" />
                        </svg>
                        (11) 99999-9999
                    </a>
                </div>
            </div>
        </section>
    </main>
@endsection
