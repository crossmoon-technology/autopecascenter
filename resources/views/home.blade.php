@extends('layouts.app')

@section('content')
    <main>
        <section class="hero">
            <div class="container hero__inner">
                <div class="hero__content">
                    <h1 class="hero__title">
                        O sistema que centraliza o seu acesso aos
                        <span class="text-accent">catálogos de autopeças</span>.
                    </h1>

                    <p class="hero__description">
                        Unificamos diversos catálogos de fornecedores em uma única plataforma para você cotar mais
                        rápido.
                    </p>

                    <ul class="hero__checklist">
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                            Acesse diversos fornecedores em um só lugar
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                            Mais agilidade para você e seus clientes
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                            Gestão de pedidos com acesso de clientes
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                            Exportação de cotações em diversos formatos
                        </li>
                    </ul>

                    <div class="hero__actions">
                        <a href="{{ route('contato') }}" class="btn btn--solid">
                            Iniciar avaliação
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12" />
                                <polyline points="12 5 19 12 12 19" />
                            </svg>
                        </a>
                        <a href="{{ route('como-funciona') }}" class="btn btn--play">
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

                        <img src="{{ asset('images/screenshots/dashboard-painel-controle.jpeg') }}"
                            alt="Painel de controle do sistema Auto Peças Center" class="browser-mock__screenshot">
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
                        <p>Cotações completas em segundos com respostas rápidas.</p>
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
                        <p>Acesso a diversos catálogos de marcas e encontre o que precisa.</p>
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
                        <p>Aumente sua conversão tendo acesso centralizado ao que você procura.</p>
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
                        <p>Busque pelo código da peça ou pelo número de conversão.</p>
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
                        <h3>Consultamos diversos catálogos</h3>
                        <p>Nosso sistema pesquisa diversos fornecedores integrados.</p>
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
                        <h3>Monte sua cotação</h3>
                        <p>Monte sua cotação e exporte do formato que desejar.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="final-cta">
            <div class="container final-cta__inner">
                <div class="final-cta__left">
                    <div class="final-cta__content">
                        <h2>Seu tempo é valioso.<br>Agilize sua <span class="text-accent">cotação com a gente.</span></h2>
                        <p>
                            Crie sua conta e descubra como o Auto Peças Center pode transformar o seu dia a dia e os
                            seus resultados.
                        </p>

                        <ul class="final-cta__benefits">
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 6 9 17l-5-5" />
                                </svg>
                                Mais produtividade
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
                    <p>Inicie uma avaliação gratuita e conheça todo o potencial do sistema.</p>
                    <a href="{{ route('contato') }}" class="btn btn--solid btn--block">Iniciar avaliação</a>
                    <p class="final-cta__or">ou fale com nosso time</p>
                    <a href="{{ route('contato') }}" class="btn btn--outline btn--block final-cta__phone">
                        Entrar em contato
                    </a>
                </div>
            </div>
        </section>
    </main>
@endsection
