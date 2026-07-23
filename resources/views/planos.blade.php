@extends('layouts.app')

@section('content')
    <main>
        @include('partials.page-hero', [
            'title' => 'Planos para o seu negócio crescer',
            'subtitle' => 'Escolha o plano ideal para o tamanho da sua operação. Sem taxas escondidas, cancele quando quiser.',
        ])

        <section class="pricing">
            <div class="container">
                <div class="pricing__grid">
                    <div class="pricing__card">
                        <h2 class="pricing__name">Básico</h2>
                        <p class="pricing__description">Para quem está começando a cotar online.</p>
                        <p class="pricing__price">R$ 99<span>/mês</span></p>
                        <a href="#agendar-demonstracao" class="btn btn--outline btn--block">Começar agora</a>
                        <ul class="pricing__features">
                            <li>Até 50 cotações por mês</li>
                            <li>Acesso a fornecedores integrados</li>
                            <li>Comparação de preços</li>
                            <li>Suporte por e-mail</li>
                        </ul>
                    </div>

                    <div class="pricing__card pricing__card--highlight">
                        <span class="pricing__badge">Mais popular</span>
                        <h2 class="pricing__name">Profissional</h2>
                        <p class="pricing__description">Para equipes que vendem todos os dias.</p>
                        <p class="pricing__price">R$ 249<span>/mês</span></p>
                        <a href="#agendar-demonstracao" class="btn btn--solid btn--block">Começar agora</a>
                        <ul class="pricing__features">
                            <li>Cotações ilimitadas</li>
                            <li>Todos os fornecedores integrados</li>
                            <li>Comparação e histórico de preços</li>
                            <li>Relatórios e métricas de vendas</li>
                            <li>Suporte prioritário</li>
                        </ul>
                    </div>

                    <div class="pricing__card">
                        <h2 class="pricing__name">Empresarial</h2>
                        <p class="pricing__description">Para redes e operações de grande porte.</p>
                        <p class="pricing__price">Sob consulta</p>
                        <a href="#agendar-demonstracao" class="btn btn--outline btn--block">Falar com vendas</a>
                        <ul class="pricing__features">
                            <li>Tudo do plano Profissional</li>
                            <li>Múltiplas filiais e usuários</li>
                            <li>Integrações personalizadas</li>
                            <li>Gerente de conta dedicado</li>
                            <li>Treinamento da equipe</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        @include('partials.cta-banner', [
            'title' => 'Ainda com dúvidas sobre qual plano escolher?',
            'subtitle' => 'Fale com nosso time e encontre a melhor opção para sua empresa.',
            'buttonLabel' => 'Falar com um especialista',
            'buttonUrl' => route('contato'),
        ])
    </main>
@endsection
