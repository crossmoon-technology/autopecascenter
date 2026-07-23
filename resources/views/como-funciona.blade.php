@extends('layouts.app')

@section('content')
    <main>
        @include('partials.page-hero', [
            'title' => 'Como a Auto Peças Center funciona',
            'subtitle' => 'Do pedido à melhor proposta, em poucos passos simples.',
        ])

        <section class="detailed-steps">
            <div class="container">
                <div class="detailed-steps__item">
                    <img src="{{ asset('images/screenshots/pecas.jpg') }}"
                        alt="Tela de busca de peças do sistema Auto Peças Center" class="detailed-steps__media">
                    <div class="detailed-steps__content">
                        <span class="detailed-steps__badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            Passo 1
                        </span>
                        <h2>Você busca a peça</h2>
                        <p>
                            A busca é feita apenas pelo código da peça ou pelo número de conversão, garantindo que
                            você encontre exatamente o item correto.
                        </p>
                    </div>
                </div>

                <div class="detailed-steps__item detailed-steps__item--reverse">
                    <img src="{{ asset('images/screenshots/catalogo.jpg') }}"
                        alt="Tela de catálogos do sistema Auto Peças Center" class="detailed-steps__media">
                    <div class="detailed-steps__content">
                        <span class="detailed-steps__badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="18" cy="5" r="3" />
                                <circle cx="6" cy="12" r="3" />
                                <circle cx="18" cy="19" r="3" />
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49" />
                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49" />
                            </svg>
                            Passo 2
                        </span>
                        <h2>Consultamos diversos catálogos</h2>
                        <p>
                            Nosso sistema pesquisa diversos fornecedores integrados ao mesmo tempo, garantindo
                            agilidade e maior satisfação dos clientes.
                        </p>
                    </div>
                </div>

                <div class="detailed-steps__item">
                    <img src="{{ asset('images/screenshots/cotacao.jpg') }}"
                        alt="Tela de cotação do sistema Auto Peças Center" class="detailed-steps__media">
                    <div class="detailed-steps__content">
                        <span class="detailed-steps__badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                                <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                            </svg>
                            Passo 3
                        </span>
                        <h2>Monte sua cotação</h2>
                        <p>
                            Monte sua cotação e exporte do formato que desejar.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        @include('partials.cta-banner', [
            'title' => 'Pronto para ver funcionando?',
            'subtitle' => 'Inicie uma avaliação gratuita e conheça todo o potencial do sistema.',
        ])
    </main>
@endsection
