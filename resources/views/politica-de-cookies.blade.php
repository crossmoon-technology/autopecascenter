@extends('layouts.app')

@section('content')
    <main>
        @include('partials.page-hero', [
            'title' => 'Política de Cookies',
            'subtitle' => 'O que são cookies e como os usamos na Auto Peças Center.',
        ])

        <section class="legal">
            <div class="container">
                <p class="legal__updated-at">Última atualização: {{ \Illuminate\Support\Carbon::parse('2026-07-26')->translatedFormat('d \d\e F \d\e Y') }}</p>

                <div class="legal__content">
                    <h2>1. O que são cookies</h2>
                    <p>
                        Cookies são pequenos arquivos de texto armazenados no seu navegador quando você visita um
                        site. Eles permitem que o site reconheça seu dispositivo e lembre de informações sobre sua
                        visita, como preferências e sessão de login.
                    </p>

                    <h2>2. Como usamos cookies</h2>
                    <p>
                        Usamos apenas cookies essenciais, necessários para o funcionamento básico do site e da
                        plataforma:
                    </p>
                    <ul>
                        <li><strong>Sessão e autenticação:</strong> mantêm você conectado enquanto navega pela plataforma, depois de fazer login.</li>
                        <li><strong>Segurança:</strong> ajudam a proteger formulários contra envios indevidos (proteção CSRF).</li>
                        <li><strong>Preferências:</strong> lembram que você já confirmou a leitura deste aviso de cookies, para não exibi-lo novamente a cada visita.</li>
                    </ul>
                    <p>
                        Esses cookies são indispensáveis para que o site funcione corretamente e não podem ser
                        desativados através das nossas configurações — eles não são usados para rastreamento ou
                        publicidade.
                    </p>

                    <h2>3. Cookies de terceiros</h2>
                    <p>
                        Não utilizamos cookies de rastreamento, análise de comportamento ou publicidade de
                        terceiros nesta plataforma.
                    </p>

                    <h2>4. Como gerenciar cookies no seu navegador</h2>
                    <p>
                        A maioria dos navegadores permite controlar ou apagar cookies através das configurações de
                        privacidade. Note que, como usamos apenas cookies essenciais, bloqueá-los pode impedir o
                        funcionamento correto do login e de outras funcionalidades da plataforma.
                    </p>

                    <h2>5. Alterações nesta política</h2>
                    <p>
                        Podemos atualizar esta Política de Cookies periodicamente. A data da última atualização é
                        sempre indicada no topo desta página. Para mais informações sobre como tratamos seus dados
                        pessoais de forma geral, consulte nossa <a href="{{ route('privacy-policy') }}">Política de Privacidade</a>.
                    </p>

                    <h2>6. Contato</h2>
                    <p>
                        Dúvidas sobre esta política? Fale com a gente:
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
