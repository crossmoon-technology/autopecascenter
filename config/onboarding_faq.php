<?php

// Conteúdo da página de Perguntas frequentes (ver App\Filament\Pages\Ajuda\Faq e
// App\Filament\Client\Pages\Faq), agrupado do mesmo jeito que o tutorial guiado (ver
// App\Livewire\OnboardingTutorial): "seller" cobre Admin e SuperAdmin (o painel do
// vendedor), "client" cobre o Cliente. De propósito fora do banco — é conteúdo estático
// de ajuda, não dado de negócio, então não precisa de migration/seeder pra isso.

return [

    'seller' => [
        [
            'question' => 'Como eu busco uma peça?',
            'answer' => 'Pelo menu Buscas: direto no site do fabricante (Iframes), na nossa Base de dados ou pela API. Toda busca que você fizer fica salva no Histórico, e as peças que você mais usa dá pra deixar favoritadas.',
        ],
        [
            'question' => 'Como eu convido um cliente novo?',
            'answer' => 'Em Clientes, gere um link de cadastro — cada cliente recebe um link só dele pra criar a própria conta e já ficar vinculado a você.',
        ],
        [
            'question' => 'Como eu vejo os pedidos que meus clientes enviaram?',
            'answer' => 'Em Pedidos, você acompanha o status de cada um. Se um cliente preferir ligar ou mandar mensagem em vez de usar o painel, também dá pra registrar o pedido dele por lá, na mão.',
        ],
        [
            'question' => 'Dá pra eu montar uma cotação pra um cliente?',
            'answer' => 'Sim — em Cotações, você monta a lista de peças e valores e exporta em PDF, CSV ou planilha pra enviar pra ele.',
        ],
        [
            'question' => 'Como eu escondo um fabricante que eu não trabalho?',
            'answer' => 'Em Configurações > Fabricantes habilitados, desmarque os que você não quer que apareçam nas buscas e nos pedidos dos seus clientes.',
        ],
        [
            'question' => 'O que é "Buscas sem resultado"?',
            'answer' => 'Fica em Configurações — é a lista de peças que foram buscadas (por você ou pelos seus clientes) e não deram resultado. Bom pra saber o que vale a pena cadastrar.',
        ],
        [
            'question' => 'Perdi o tutorial inicial, dá pra ver de novo?',
            'answer' => 'Dá sim — é só clicar aqui no "?" no topo e escolher "Iniciar tutorial" outra vez, quando quiser.',
        ],
    ],

    'client' => [
        [
            'question' => 'Como eu faço um pedido?',
            'answer' => 'Em Criar pedido, adicione as peças (código, quantidade e, se quiser, um fabricante de preferência) e clique em "Enviar pedido".',
        ],
        [
            'question' => 'Como eu acompanho o status do meu pedido?',
            'answer' => 'Em Histórico de pedidos, você vê o status de cada pedido enviado: pendente, em andamento, finalizado ou cancelado.',
        ],
        [
            'question' => 'Posso cancelar um pedido depois de enviado?',
            'answer' => 'Sim, enquanto ele ainda não foi finalizado. Basta abrir o Histórico de pedidos e clicar em "Cancelar pedido".',
        ],
        [
            'question' => 'Pra que serve escolher um fabricante de preferência?',
            'answer' => 'É totalmente opcional — ajuda quem vai atender seu pedido a saber qual marca você prefere pra aquela peça, na ordem que você escolher.',
        ],
        [
            'question' => 'Onde vejo quais fabricantes estão disponíveis?',
            'answer' => 'Em Fabricantes — a lista mostra as marcas habilitadas por quem te convidou pra plataforma.',
        ],
        [
            'question' => 'Perdi o tutorial inicial, dá pra ver de novo?',
            'answer' => 'Dá sim — clique no "?" no topo e escolha "Iniciar tutorial" outra vez, quando quiser.',
        ],
    ],

];
