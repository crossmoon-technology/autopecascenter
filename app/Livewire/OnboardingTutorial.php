<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Filament\Client\Pages\CreateOrder;
use App\Filament\Client\Pages\Manufacturers as ClientManufacturers;
use App\Filament\Client\Pages\OrderHistory;
use App\Filament\Pages\Buscas\CatalogDatabaseSearch;
use App\Filament\Pages\Buscas\Clients;
use App\Filament\Pages\Buscas\Iframes;
use App\Filament\Pages\Buscas\Orders;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tour guiado (driver.js — ver resources/js/onboarding-tour.js) exibido na primeira vez
 * que o usuário entra no painel (ver User::needsTutorial()). Só monta os DADOS aqui —
 * quem efetivamente desenha o destaque/navega entre páginas é o JS, já que passos em
 * páginas diferentes exigem um reload de página de verdade (esse painel não roda em modo
 * SPA — ver App\Providers\Filament\*PanelProvider, nenhum chama ->spa()) e esse
 * componente Livewire não sobrevive a isso.
 *
 * O vendedor recebe um tour "linear" simples (destaca um item por vez, Próximo avança).
 * O cliente recebe um tour "guiado por pedido": ele de fato cria um pedido de teste,
 * confere o status e cancela — ver clientFlow(). Esse fluxo avança sozinho conforme os
 * eventos order-created/order-cancelled (disparados em CreateOrder::save() e
 * OrderHistory::cancelOrder()), não por cliques de "Próximo", então tem um formato
 * diferente do tour linear (ver steps()/type).
 */
class OnboardingTutorial extends Component
{
    public bool $visible = false;

    public function mount(): void
    {
        // Não mostra o tour junto com o modal de consentimento LGPD (ver
        // App\Livewire\LgpdConsent) — só depois desse aceite, via startAfterLgpdConsent()
        // abaixo. Um usuário que já aceitou antes mas ainda não terminou o tutorial (ex:
        // fechou o navegador no meio) continua caindo aqui normalmente.
        $this->visible = Auth::check()
            && Auth::user()->needsTutorial()
            && ! Auth::user()->needsLgpdConsent();
    }

    /**
     * $orderId é o pedido de teste criado durante o tour do cliente (ver clientFlow) —
     * removido junto porque não é um pedido de verdade, só serviu pro passo a passo.
     * whereKey já escopa pro usuário logado, então um id de outra conta é ignorado.
     */
    public function finish(?int $orderId = null): void
    {
        if ($orderId) {
            Auth::user()->orders()->whereKey($orderId)->delete();
        }

        Auth::user()->markTutorialCompleted();

        $this->visible = false;
    }

    /**
     * Disparado pelo App\Livewire\HelpMenu (botão "?" no topbar) quando o usuário pede
     * pra rever o tutorial manualmente — reaproveita a mesma lógica de steps() e só passa
     * a bola pro JS via um evento novo, já que o carregamento inicial (onboarding-tour:ready)
     * só dispara uma vez, no mount da página.
     */
    #[On('restart-onboarding-tour')]
    public function restart(): void
    {
        $this->dispatch('onboarding-tour:restart', steps: $this->steps());
    }

    /**
     * Disparado pelo App\Livewire\LgpdConsent assim que o usuário aceita — só a partir
     * daí o tour pode começar (ver mount() acima). Se o usuário já tinha terminado o
     * tutorial antes (ex: reaceitando um termo atualizado), não faz nada aqui.
     */
    #[On('lgpd-accepted')]
    public function startAfterLgpdConsent(): void
    {
        if (Auth::user()?->needsTutorial()) {
            $this->dispatch('onboarding-tour:restart', steps: $this->steps());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function steps(): array
    {
        return Auth::user()?->role === Role::Client
            ? $this->clientFlow()
            : ['type' => 'linear', 'steps' => $this->sellerSteps()];
    }

    /**
     * Os 6 primeiros passos apontam pra links da sidebar (visível em qualquer página do
     * painel), então cabem todos na página atual sem navegar. Só o último exige a
     * navegação de verdade — até Pedidos, pra destacar o botão "Novo pedido" (ver
     * App\Filament\Pages\Buscas\Orders, id onboarding-target-create-order).
     *
     * As buscas são 2 menus diferentes (Iframes e Base de dados), cada um com sua
     * própria forma de buscar — por isso um passo pra cada um, em vez de um só passo
     * genérico apontando só pro Iframes enquanto o texto menciona o outro sem
     * destacar onde ele fica.
     *
     * @return array<int, array{selector: string, url: string, title: string, description: string}>
     */
    private function sellerSteps(): array
    {
        $panel = $this->panelId();
        $dashboardUrl = Dashboard::getUrl(panel: $panel);
        $iframesUrl = Iframes::getUrl(panel: $panel);
        $catalogDatabaseSearchUrl = CatalogDatabaseSearch::getUrl(panel: $panel);
        $clientesUrl = Clients::getUrl(panel: $panel);
        $pedidosUrl = Orders::getUrl(panel: $panel);

        return [
            [
                'selector' => 'a[href="'.$dashboardUrl.'"]',
                'url' => $dashboardUrl,
                'title' => 'Oi, seja bem-vindo!',
                'description' => 'Deixa a gente te mostrar rapidinho como o painel funciona — não demora nada.',
            ],
            [
                'selector' => 'a[href="'.$iframesUrl.'"]',
                'url' => $dashboardUrl,
                'title' => 'Busca direto no site do fabricante',
                'description' => 'Em Iframes, a busca acontece direto no site de fabricantes como Cofap e Hipper Freios, sem sair do painel.',
            ],
            [
                'selector' => 'a[href="'.$catalogDatabaseSearchUrl.'"]',
                'url' => $dashboardUrl,
                'title' => 'Também dá pra buscar na nossa base',
                'description' => 'Em Base de dados, a busca é nos catálogos e peças já cadastrados aqui na plataforma. Em qualquer uma dessas buscas, tudo fica guardado no Histórico, e as peças que você mais usa dá pra deixar favoritadas.',
            ],
            [
                'selector' => 'a[href="'.$clientesUrl.'"]',
                'url' => $dashboardUrl,
                'title' => 'Seus clientes ficam aqui',
                'description' => 'É só convidar — cada cliente recebe um linkzinho de cadastro só dele.',
            ],
            [
                'selector' => 'a[href="'.$pedidosUrl.'"]',
                'url' => $dashboardUrl,
                'title' => 'E os pedidos deles chegam aqui',
                'description' => 'Assim que um cliente enviar uma lista, é aqui que você acompanha tudo. Clica em "Próximo" que a gente te mostra como criar um pedido você mesmo, se precisar.',
            ],
            [
                'selector' => '#onboarding-target-create-order',
                'url' => $pedidosUrl,
                'title' => 'Também dá pra criar na mão',
                'description' => 'Se um cliente ligar ou mandar mensagem em vez de usar o painel, é só registrar o pedido dele por aqui.',
            ],
        ];
    }

    /**
     * Pedido de teste, do início ao fim: mostra os fabricantes disponíveis, cria um
     * pedido (CreateOrder), confere o status e cancela (OrderHistory) — a limpeza do
     * pedido acontece em finish() quando o tour termina.
     *
     * @return array<string, mixed>
     */
    private function clientFlow(): array
    {
        $panel = $this->panelId();
        $dashboardUrl = Dashboard::getUrl(panel: $panel);
        $createOrderUrl = CreateOrder::getUrl(panel: $panel);
        $historyUrl = OrderHistory::getUrl(panel: $panel);
        $manufacturersUrl = ClientManufacturers::getUrl(panel: $panel);

        return [
            'type' => 'guided-order',
            'urls' => [
                'manufacturers' => $manufacturersUrl,
                'createOrder' => $createOrderUrl,
                'history' => $historyUrl,
            ],
            'intro' => [
                'selector' => 'a[href="'.$manufacturersUrl.'"]',
                'url' => $dashboardUrl,
                'title' => 'Oi, seja bem-vindo!',
                'description' => 'Vamos fazer um pedido de teste juntos, bem rapidinho, só pra você pegar o jeito: criar, acompanhar e até cancelar. Primeiro, uma olhada rápida nos fabricantes disponíveis.',
            ],
            'manufacturersStep' => [
                'selector' => '.cm-manufacturers-grid',
                'url' => $manufacturersUrl,
                'title' => 'Esses são os fabricantes disponíveis',
                'description' => 'Na hora de montar seu pedido, você pode escolher um fabricante de preferência pra cada peça.',
            ],
            // Passo intermediário, ainda na página de Fabricantes — aponta pro link de
            // Criar pedido antes de navegar pra lá, pra combinar com o que o próprio
            // texto acabou de anunciar (evita o desalinhamento de apontar pra um link e
            // navegar pra outro lugar).
            'goToCreateOrderStep' => [
                'selector' => 'a[href="'.$createOrderUrl.'"]',
                'title' => 'Agora vamos criar um pedido de teste',
                'description' => 'Clique em "Próximo" pra ir até lá.',
            ],
            // Detalha campo por campo antes de chegar no botão de enviar (createStep) —
            // ver os seletores/classes onboarding-target-* em
            // App\Filament\Client\Pages\CreateOrder.
            'createFieldSteps' => [
                [
                    'selector' => '.onboarding-target-description',
                    'title' => 'Código da peça',
                    'description' => 'Digite aqui o código ou o nome da peça que você precisa. Pode ser qualquer coisa agora, é só um teste.',
                ],
                [
                    'selector' => '.onboarding-target-quantity',
                    'title' => 'Quantidade',
                    'description' => 'Quantas unidades dessa peça você precisa.',
                ],
                [
                    'selector' => '.onboarding-target-manufacturers',
                    'title' => 'Fabricante de preferência',
                    'description' => 'Esse aqui é opcional — se quiser, escolha um ou mais fabricantes na ordem que você prefere.',
                    // O dropdown desse select abre pra baixo — sem isso, o card do
                    // tutorial (que também nasce embaixo por padrão) fica em cima das
                    // opções, escondendo elas.
                    'side' => 'top',
                    'align' => 'center',
                ],
            ],
            'createStep' => [
                'selector' => '.mo-submit',
                'url' => $createOrderUrl,
                'title' => 'Pronto pra enviar',
                'description' => 'Depois de preencher, clique em "Enviar pedido" pra continuar.',
            ],
            'verifyStep' => [
                'title' => 'Esse é o status do seu pedido',
                'description' => 'Assim que o vendedor mexer nele do outro lado, é aqui que você vê a mudança.',
            ],
            'cancelStep' => [
                'title' => 'Agora vamos cancelar',
                'description' => 'Clique em "Cancelar pedido" aqui embaixo — é só pra você ver como funciona.',
            ],
            'doneStep' => [
                'title' => 'Prontinho!',
                'description' => 'Esse pedido era só um teste, então já vamos apagar ele. Agora é só criar os seus pedidos de verdade quando precisar.',
            ],
        ];
    }

    /**
     * Page::getUrl() normalmente detecta o panel atual sozinho a partir do request, mas
     * isso depende de um contexto de panel totalmente montado (middleware etc.) que um
     * teste de componente Livewire isolado não reproduz — resolvendo pelo role (ver
     * App\Enums\Role::panelId()) fica correto nos dois casos, sem depender de detecção
     * implícita.
     */
    private function panelId(): string
    {
        return Auth::user()->role->panelId();
    }

    public function render(): View
    {
        // Sempre calcula os steps (não só quando $visible) — um reinício manual (ver
        // App\Livewire\HelpMenu) pode ter navegado pra outra página no meio do caminho
        // (ver window.location.href em onboarding-tour.js), e nesse novo carregamento de
        // página needsTutorial já vem false (o usuário concluiu o tutorial antes) — o JS
        // ainda precisa dos dados dos passos pra retomar de onde parou.
        return view('livewire.onboarding-tutorial', [
            'steps' => Auth::check() ? $this->steps() : [],
        ]);
    }
}
