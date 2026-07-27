@extends('layouts.app')

@section('content')
    <main>
        @include('partials.page-hero', [
            'title' => 'Política de Privacidade',
            'subtitle' => 'Como coletamos, usamos e protegemos seus dados pessoais na Auto Peças Center.',
        ])

        <section class="legal">
            <div class="container">
                <p class="legal__updated-at">Última atualização: {{ \Illuminate\Support\Carbon::parse('2026-07-26')->translatedFormat('d \d\e F \d\e Y') }}</p>

                <div class="legal__content">
                    <h2>1. Introdução</h2>
                    <p>
                        Esta Política de Privacidade descreve como a Auto Peças Center coleta, usa, armazena e
                        protege os dados pessoais de quem utiliza nosso site e nossa plataforma, em conformidade
                        com a Lei Geral de Proteção de Dados Pessoais (Lei nº 13.709/2018 — LGPD).
                    </p>
                    <p>
                        Ao utilizar nossos serviços, você concorda com as práticas descritas aqui. Recomendamos a
                        leitura também da nossa <a href="{{ route('cookie-policy') }}">Política de Cookies</a> e
                        dos <a href="{{ route('terms-of-use') }}">Termos de Uso</a>.
                    </p>

                    <h2>2. Quem somos</h2>
                    <p>
                        A Auto Peças Center é a controladora dos dados pessoais tratados através deste site e da
                        plataforma de cotação de autopeças. Para qualquer dúvida sobre este documento ou sobre o
                        tratamento dos seus dados, entre em contato pelos canais listados na seção "Contato" ao
                        final desta página.
                    </p>

                    <h2>3. Quais dados coletamos</h2>
                    <p>Dependendo de como você usa nosso site e plataforma, podemos coletar:</p>
                    <ul>
                        <li><strong>Dados de cadastro:</strong> nome, e-mail, telefone, CPF/CNPJ e senha (armazenada de forma criptografada).</li>
                        <li><strong>Dados de uso da plataforma:</strong> buscas de peças realizadas, cotações montadas, pedidos e histórico de navegação dentro do sistema.</li>
                        <li><strong>Dados de contato:</strong> informações enviadas voluntariamente através do nosso formulário de contato.</li>
                        <li><strong>Dados técnicos:</strong> endereço IP, tipo de navegador e cookies essenciais de sessão — veja detalhes na <a href="{{ route('cookie-policy') }}">Política de Cookies</a>.</li>
                    </ul>

                    <h2>4. Como usamos seus dados</h2>
                    <p>Usamos os dados coletados exclusivamente para finalidades legítimas relacionadas à operação do serviço, como:</p>
                    <ul>
                        <li>Criar e manter sua conta na plataforma;</li>
                        <li>Viabilizar buscas de peças, cotações e pedidos entre você e os fornecedores integrados;</li>
                        <li>Enviar comunicações operacionais sobre sua conta, pedidos ou assinatura;</li>
                        <li>Responder a solicitações feitas através do formulário de contato;</li>
                        <li>Cumprir obrigações legais e regulatórias aplicáveis.</li>
                    </ul>
                    <p>Não vendemos nem compartilhamos seus dados pessoais com terceiros para fins de publicidade.</p>

                    <h2>5. Armazenamento e segurança</h2>
                    <p>
                        Seus dados são armazenados em banco de dados protegido, com acesso restrito à equipe
                        responsável pela operação da plataforma. Senhas são armazenadas apenas em formato
                        criptografado (hash), nunca em texto puro. Adotamos medidas técnicas e organizacionais
                        razoáveis para proteger seus dados contra acesso não autorizado, perda ou alteração
                        indevida.
                    </p>
                    <p>
                        Mantemos seus dados pelo tempo necessário para cumprir as finalidades descritas nesta
                        política ou conforme exigido por obrigações legais, contábeis ou regulatórias. Após esse
                        período, os dados são excluídos ou anonimizados.
                    </p>

                    <h2>6. Compartilhamento de dados</h2>
                    <p>
                        Dados de cotações e pedidos podem ser compartilhados com os fornecedores/fabricantes
                        envolvidos na transação, na medida necessária para viabilizar a compra e venda de peças.
                        Também podemos compartilhar dados com prestadores de serviço que apoiam a operação da
                        plataforma (por exemplo, provedores de e-mail e infraestrutura), sempre sob obrigação de
                        confidencialidade.
                    </p>

                    <h2>7. Seus direitos como titular de dados (LGPD)</h2>
                    <p>De acordo com a LGPD, você pode, a qualquer momento, solicitar:</p>
                    <ul>
                        <li>Confirmação da existência de tratamento dos seus dados;</li>
                        <li>Acesso aos dados que temos sobre você;</li>
                        <li>Correção de dados incompletos, inexatos ou desatualizados;</li>
                        <li>Anonimização, bloqueio ou eliminação de dados desnecessários ou excessivos;</li>
                        <li>Portabilidade dos seus dados a outro fornecedor de serviço;</li>
                        <li>Eliminação dos dados pessoais tratados com o seu consentimento, quando aplicável;</li>
                        <li>Revogação do consentimento, quando o tratamento for baseado nele.</li>
                    </ul>
                    <p>Para exercer qualquer um desses direitos, entre em contato pelos canais abaixo.</p>

                    <h2>8. Alterações nesta política</h2>
                    <p>
                        Podemos atualizar esta Política de Privacidade periodicamente para refletir mudanças em
                        nossas práticas ou na legislação aplicável. A data da última atualização é sempre indicada
                        no topo desta página.
                    </p>

                    <h2>9. Contato</h2>
                    <p>
                        Para dúvidas, solicitações relacionadas aos seus dados pessoais ou qualquer assunto
                        relacionado a esta política, fale com a gente:
                    </p>
                    <ul>
                        <li>E-mail: {{ \App\Models\Setting::get('contact_email', 'contato@autopecascenter.com.br') }}</li>
                        <li>Telefone: {{ \App\Models\Setting::get('contact_phone', '(11) 99999-9999') }}</li>
                        <li>Ou pela nossa <a href="{{ route('contato') }}">página de contato</a>.</li>
                    </ul>
                </div>
            </div>
        </section>
    </main>
@endsection
