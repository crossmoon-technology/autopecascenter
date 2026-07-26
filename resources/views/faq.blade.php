@extends('layouts.app')

@section('content')
    <main>
        @include('partials.page-hero', [
            'title' => 'Perguntas frequentes',
            'subtitle' => 'Tudo que você precisa saber antes de começar a usar a Auto Peças Center.',
        ])

        <section class="faq">
            <div class="container">
                <div class="faq__list">
                    <details class="faq__item" open>
                        <summary class="faq__question">O que é a Auto Peças Center?</summary>
                        <div class="faq__answer">
                            É o sistema que conecta sua empresa a diversos catálogos de autopeças, permitindo
                            buscar peças, comparar preços entre fornecedores e montar cotações para seus clientes
                            em um só lugar.
                        </div>
                    </details>

                    <details class="faq__item">
                        <summary class="faq__question">Como funciona a avaliação gratuita?</summary>
                        <div class="faq__answer">
                            São 7 dias com acesso completo ao plano Profissional, sem necessidade de cartão de
                            crédito e sem compromisso. Ao final do período, é só escolher o plano que fizer mais
                            sentido para o seu negócio.
                        </div>
                    </details>

                    <details class="faq__item">
                        <summary class="faq__question">Quais planos estão disponíveis?</summary>
                        <div class="faq__answer">
                            Temos os planos Básico e Profissional, com diferenças no volume de cotações e nos
                            recursos disponíveis. Veja os detalhes e valores atualizados na
                            <a href="{{ route('planos') }}">página de planos</a>.
                        </div>
                    </details>

                    <details class="faq__item">
                        <summary class="faq__question">Posso cancelar quando quiser?</summary>
                        <div class="faq__answer">
                            Sim. Não há taxas escondidas nem fidelidade — você pode cancelar sua assinatura a
                            qualquer momento.
                        </div>
                    </details>

                    <details class="faq__item">
                        <summary class="faq__question">Quais fornecedores estão integrados?</summary>
                        <div class="faq__answer">
                            A plataforma já vem integrada a diversos fabricantes de autopeças, permitindo buscar
                            e comparar preços entre eles sem precisar acessar cada site separadamente.
                        </div>
                    </details>

                    <details class="faq__item">
                        <summary class="faq__question">Como funciona o suporte?</summary>
                        <div class="faq__answer">
                            Todos os planos contam com suporte por e-mail, e o plano Profissional tem suporte
                            prioritário. Você também pode falar com nosso time pela
                            <a href="{{ route('contato') }}">página de contato</a>.
                        </div>
                    </details>
                </div>
            </div>
        </section>

        @include('partials.cta-banner', [
            'title' => 'Ainda tem alguma dúvida?',
            'subtitle' => 'Fale com nosso time e a gente te ajuda a decidir.',
            'buttonLabel' => 'Falar com um especialista',
            'buttonUrl' => route('contato'),
        ])
    </main>
@endsection
