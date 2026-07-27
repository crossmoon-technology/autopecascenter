@extends('layouts.app')

@section('content')
    <main>
        @include('partials.page-hero', [
            'title' => 'Termos de Uso',
            'subtitle' => 'As regras de utilização da plataforma Auto Peças Center.',
        ])

        <section class="legal">
            <div class="container">
                <p class="legal__updated-at">Última atualização: {{ \Illuminate\Support\Carbon::parse('2026-07-26')->translatedFormat('d \d\e F \d\e Y') }}</p>

                <div class="legal__content">
                    <h2>1. Aceitação dos termos</h2>
                    <p>
                        Ao acessar ou usar o site e a plataforma da Auto Peças Center, você concorda com estes
                        Termos de Uso e com nossa <a href="{{ route('privacy-policy') }}">Política de Privacidade</a>.
                        Se você não concorda com algum destes termos, não deve utilizar nossos serviços.
                    </p>

                    <h2>2. Descrição do serviço</h2>
                    <p>
                        A Auto Peças Center é uma plataforma que conecta vendedores e clientes de autopeças,
                        permitindo buscar peças em diversos catálogos, comparar informações entre fabricantes e
                        montar cotações e pedidos.
                    </p>

                    <h2>3. Cadastro e conta</h2>
                    <p>
                        Para usar determinadas funcionalidades, é necessário criar uma conta, fornecendo
                        informações verdadeiras, completas e atualizadas. Você é responsável por manter a
                        confidencialidade da sua senha e por todas as atividades realizadas na sua conta.
                    </p>

                    <h2>4. Planos, avaliação gratuita e cancelamento</h2>
                    <p>
                        Vendedores podem contar com um período de avaliação gratuita antes de escolher um plano
                        pago. Os planos disponíveis, seus preços e recursos estão descritos na nossa
                        <a href="{{ route('planos') }}">página de planos</a>. Assinaturas podem ser canceladas a
                        qualquer momento, sem taxas ou fidelidade.
                    </p>

                    <h2>5. Responsabilidades do usuário</h2>
                    <p>Ao usar a plataforma, você concorda em:</p>
                    <ul>
                        <li>Fornecer informações verdadeiras no cadastro e nas cotações/pedidos realizados;</li>
                        <li>Não utilizar a plataforma para fins ilícitos ou fraudulentos;</li>
                        <li>Não tentar acessar áreas restritas do sistema sem autorização;</li>
                        <li>Respeitar os demais usuários e fornecedores integrados à plataforma.</li>
                    </ul>

                    <h2>6. Propriedade intelectual</h2>
                    <p>
                        O site, a plataforma, sua marca, layout e funcionalidades são de propriedade da Auto Peças
                        Center e protegidos por leis de propriedade intelectual. É proibida a reprodução, cópia ou
                        distribuição de qualquer parte do serviço sem autorização prévia.
                    </p>

                    <h2>7. Limitação de responsabilidade</h2>
                    <p>
                        A Auto Peças Center atua como intermediária na conexão entre vendedores e clientes,
                        agregando informações de catálogos de terceiros. Não nos responsabilizamos por
                        divergências de preço, disponibilidade ou especificação de peças informadas pelos
                        fabricantes/fornecedores integrados, tampouco por transações realizadas diretamente entre
                        vendedores e clientes fora da plataforma.
                    </p>

                    <h2>8. Alterações nestes termos</h2>
                    <p>
                        Podemos atualizar estes Termos de Uso periodicamente, para refletir mudanças no serviço ou
                        na legislação aplicável. A data da última atualização é sempre indicada no topo desta
                        página. O uso continuado da plataforma após uma alteração implica concordância com os
                        novos termos.
                    </p>

                    <h2>9. Lei aplicável</h2>
                    <p>
                        Estes Termos de Uso são regidos pelas leis brasileiras. Eventuais controvérsias serão
                        resolvidas no foro do domicílio do consumidor, conforme aplicável.
                    </p>

                    <h2>10. Contato</h2>
                    <p>
                        Dúvidas sobre estes termos? Fale com a gente:
                    </p>
                    <ul>
                        <li>E-mail: {{ \App\Models\Setting::get('contact_email', 'contato@autopecascenter.com.br') }}</li>
                        <li>Ou pela nossa <a href="{{ route('contato') }}">página de contato</a>.</li>
                    </ul>
                </div>
            </div>
        </section>
    </main>
@endsection
