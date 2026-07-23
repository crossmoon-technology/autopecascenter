import { driver } from 'driver.js';

const LINEAR_STEP_KEY = 'apc-onboarding-tour-step';
const ORDER_PHASE_KEY = 'apc-onboarding-order-phase';
const ORDER_ID_KEY = 'apc-onboarding-order-id';

function clearState() {
    localStorage.removeItem(LINEAR_STEP_KEY);
    localStorage.removeItem(ORDER_PHASE_KEY);
    localStorage.removeItem(ORDER_ID_KEY);
}

// Depois que o usuário já concluiu o tutorial uma vez (tutorial_completed_at preenchido),
// o carregamento normal da página (needsTutorial vindo do servidor) não sabe que um
// reinício manual (botão "?" > Iniciar tutorial, ver App\Livewire\HelpMenu) está em
// andamento — só o localStorage sabe disso, quando a página certa exige uma navegação de
// verdade no meio do caminho (ver os window.location.href abaixo). Por isso o "ready" do
// carregamento de página também resume o tour se achar essa marca, mesmo com
// needsTutorial: false.
function hasActiveTourState() {
    return localStorage.getItem(LINEAR_STEP_KEY) !== null || localStorage.getItem(ORDER_PHASE_KEY) !== null;
}

function pathOf(url) {
    try {
        return new URL(url, window.location.origin).pathname;
    } catch {
        return url;
    }
}

function unionRect(a, b) {
    if (!b) {
        return a;
    }

    const left = Math.min(a.left, b.left);
    const top = Math.min(a.top, b.top);
    const right = Math.max(a.right, b.right);
    const bottom = Math.max(a.bottom, b.bottom);

    return { left, top, right, bottom, width: right - left, height: bottom - top };
}

/**
 * O select de fabricantes (passo "Fabricante de preferência") abre um dropdown pra baixo,
 * mas o driver.js recorta o overlay escurecido só com base no retângulo do próprio
 * wrapper — que não cresce quando o dropdown abre (é um elemento posicionado por fora do
 * fluxo normal), então a área das opções continua coberta pelo overlay e bloqueia o
 * clique nelas mesmo com overlayClickBehavior neutralizado.
 *
 * Uma primeira tentativa redimensionava o próprio wrapper (min-height) pra "esticar" seu
 * retângulo real — mas isso mexe no layout de verdade do campo, e a lib que posiciona o
 * dropdown reage a isso (ela mesma observa o tamanho/posição do wrapper), entrando num
 * ciclo que fechava o dropdown sozinho ao clicar numa opção. Em vez disso, um elemento
 * "proxy" (fixed, fora do fluxo, sem nenhum vínculo com o wrapper de verdade) é quem dá ao
 * driver.js o retângulo maior — o wrapper de verdade nunca é tocado. Como o proxy não é
 * ancestral do wrapper/listbox no DOM, replicamos manualmente a classe que o driver.js usa
 * pra reabilitar cliques (driver-active-element) no wrapper de verdade, senão o clique nas
 * opções continua bloqueado mesmo com o recorte já do tamanho certo.
 */
function manufacturersProxyElement() {
    let proxy = document.getElementById('onboarding-manufacturers-proxy');

    if (!proxy) {
        proxy = document.createElement('div');
        proxy.id = 'onboarding-manufacturers-proxy';
        proxy.style.position = 'fixed';
        proxy.style.pointerEvents = 'none';
        document.body.appendChild(proxy);
    }

    return proxy;
}

function syncManufacturersProxyRect() {
    const wrapper = document.querySelector('.onboarding-target-manufacturers');

    if (!wrapper) {
        return;
    }

    // O componente mantém um [role="listbox"] no DOM mesmo fechado (0x0, escondido) —
    // sem checar o tamanho, o union pega esse retângulo zerado (ancorado em 0,0) e o
    // recorte estica do canto da página até o campo, bem maior e desalinhado do real.
    const listbox = wrapper.querySelector('[role="listbox"]');
    const listboxRect = listbox?.getBoundingClientRect();
    const isListboxOpen = !!listboxRect && listboxRect.width > 0 && listboxRect.height > 0;
    const rect = unionRect(wrapper.getBoundingClientRect(), isListboxOpen ? listboxRect : undefined);
    const proxy = manufacturersProxyElement();

    proxy.style.top = `${rect.top}px`;
    proxy.style.left = `${rect.left}px`;
    proxy.style.width = `${rect.width}px`;
    proxy.style.height = `${rect.height}px`;

    wrapper.classList.add('driver-active-element');
}

function teardownManufacturersProxy() {
    document.querySelector('.onboarding-target-manufacturers')?.classList.remove('driver-active-element');
    document.getElementById('onboarding-manufacturers-proxy')?.remove();
}

/**
 * Consecutive steps that target the current page get driven together, in one driver.js
 * run, with normal Next/Previous between them. The moment the run reaches a step whose
 * *next* step lives on a different page, that step's Next button is swapped for a real
 * navigation (storing where to resume) instead of driver.js's own step-advance — since a
 * full page load tears down this whole JS context, there's no in-page way to "continue"
 * across that boundary.
 */
function buildLinearDriverSteps(steps, startIndex, currentPath) {
    let endIndex = startIndex;

    while (endIndex + 1 < steps.length && pathOf(steps[endIndex + 1].url) === currentPath) {
        endIndex++;
    }

    const driverSteps = [];

    for (let i = startIndex; i <= endIndex; i++) {
        const step = steps[i];
        const isLastOverall = i === steps.length - 1;
        const isGroupBoundary = i === endIndex && !isLastOverall;

        const popover = {
            title: step.title,
            description: step.description,
        };

        if (isGroupBoundary) {
            popover.nextBtnText = 'Próximo';
            popover.onNextClick = () => {
                localStorage.setItem(LINEAR_STEP_KEY, String(i + 1));
                window.location.href = steps[i + 1].url;
            };
        }

        if (isLastOverall) {
            popover.doneBtnText = 'Concluir';
        }

        driverSteps.push({ element: step.selector, popover });
    }

    return driverSteps;
}

function startLinearTour(steps, onFinished) {
    if (steps.length === 0) {
        return;
    }

    let startIndex = parseInt(localStorage.getItem(LINEAR_STEP_KEY) || '0', 10);

    if (Number.isNaN(startIndex) || startIndex < 0 || startIndex >= steps.length) {
        startIndex = 0;
    }

    const currentPath = window.location.pathname;

    if (pathOf(steps[startIndex].url) !== currentPath) {
        // Persiste o índice (mesmo que seja só o valor padrão 0) antes de navegar — senão,
        // ao chegar na página certa, não sobra nenhuma marca no localStorage indicando que
        // um tour está em andamento (ver hasActiveTourState).
        localStorage.setItem(LINEAR_STEP_KEY, String(startIndex));
        window.location.href = steps[startIndex].url;

        return;
    }

    const driverSteps = buildLinearDriverSteps(steps, startIndex, currentPath);

    const driverObj = driver({
        showProgress: false,
        allowClose: true,
        overlayClickBehavior: () => {},
        // Só o X fecha o tour (ver onCloseClick/bailAndClose) — Escape não fecha mais.
        allowKeyboardControl: false,
        skipMissingElement: true,
        nextBtnText: 'Próximo',
        prevBtnText: 'Voltar',
        steps: driverSteps,
        onDestroyed: () => {
            clearState();
            onFinished();
        },
    });

    driverObj.drive();
}

/**
 * Cliente: em vez de só apontar pra links, ele de fato cria um pedido de teste, confere
 * o status e cancela — cada passo avança sozinho pelos eventos order-created/
 * order-cancelled disparados pelo Livewire (ver CreateOrder::save() e
 * OrderHistory::cancelOrder()), não por cliques de "Próximo". O id do pedido criado fica
 * em localStorage pra sobreviver às navegações de página inteira entre Criar pedido e
 * Histórico, e volta pro servidor em finish(orderId) — que apaga o pedido de teste.
 */
function startGuidedOrderTour(flow, finish) {
    const phase = localStorage.getItem(ORDER_PHASE_KEY) || 'intro';
    const currentPath = window.location.pathname;

    const bail = () => {
        const orderId = localStorage.getItem(ORDER_ID_KEY);
        clearState();
        finish(orderId ? parseInt(orderId, 10) : null);
    };

    // Providing a custom onCloseClick replaces driver.js's own close-and-destroy
    // behavior entirely — without calling opts.driver.destroy() ourselves, clicking the
    // X would run bail() but leave the spotlight/popover visually stuck on screen.
    const bailAndClose = (element, step, opts) => {
        opts?.driver?.destroy();
        bail();
    };

    if (phase === 'intro') {
        if (pathOf(flow.intro.url) !== currentPath) {
            // Mesma ideia do startLinearTour: persiste a fase (mesmo sendo só o valor
            // padrão 'intro') antes de navegar, senão a página de destino não acha
            // nenhuma marca de tour em andamento (ver hasActiveTourState).
            localStorage.setItem(ORDER_PHASE_KEY, 'intro');
            window.location.href = flow.intro.url;

            return;
        }

        driver({
            showProgress: false,
            allowClose: true,
            // Um clique fora do elemento destacado (ex: numa opção de um dropdown que se
            // expande além da área originalmente destacada, como o passo de fabricantes
            // de preferência) não deve fechar o tour inteiro — só o X faz isso (ver
            // onCloseClick/bailAndClose). Repetido em todo driver() deste fluxo.
            overlayClickBehavior: () => {},
            // Só o X fecha o tour (ver onCloseClick/bailAndClose) — Escape não fecha mais.
            allowKeyboardControl: false,
            skipMissingElement: true,
            steps: [{
                element: flow.intro.selector,
                popover: {
                    title: flow.intro.title,
                    description: flow.intro.description,
                    // Precisa estar aqui (nível do popover), não no config geral do
                    // driver — como esse é o único/último step do array, o driver.js
                    // troca o rótulo do botão pro fallback de "done" (ver doneBtnText)
                    // a menos que o popover do próprio step defina nextBtnText.
                    showButtons: ['next', 'close'],
                    nextBtnText: 'Próximo',
                    onNextClick: () => {
                        localStorage.setItem(ORDER_PHASE_KEY, 'manufacturers');
                        window.location.href = flow.manufacturersStep.url;
                    },
                },
            }],
            onCloseClick: bailAndClose,
        }).drive();

        return;
    }

    if (phase === 'manufacturers') {
        if (pathOf(flow.manufacturersStep.url) !== currentPath) {
            window.location.href = flow.manufacturersStep.url;

            return;
        }

        // Vendedor sem nenhum fabricante cadastrado ainda: não tem o que destacar aqui,
        // então pula direto pro próximo passo em vez de mostrar um popover apontando pra
        // nada (checar antes de chamar o driver.js evita a confusão de misturar esse
        // "pular" com o onCloseClick/onDestroyed do close normal, que fazem outra coisa).
        if (!document.querySelector(flow.manufacturersStep.selector)) {
            localStorage.setItem(ORDER_PHASE_KEY, 'awaiting-create');
            window.location.href = flow.createStep.url;

            return;
        }

        driver({
            showProgress: false,
            allowClose: true,
            overlayClickBehavior: () => {},
            // Só o X fecha o tour (ver onCloseClick/bailAndClose) — Escape não fecha mais.
            allowKeyboardControl: false,
            skipMissingElement: true,
            nextBtnText: 'Próximo',
            prevBtnText: 'Voltar',
            // Dois passos aqui, ainda nesta página: primeiro explica a grade de
            // fabricantes, depois aponta pro link de Criar pedido — só esse último navega
            // (ver goToCreateOrderStep), pra sempre bater o que está destacado com pra
            // onde o Próximo realmente leva.
            steps: [
                {
                    element: flow.manufacturersStep.selector,
                    popover: {
                        title: flow.manufacturersStep.title,
                        description: flow.manufacturersStep.description,
                    },
                },
                {
                    element: flow.goToCreateOrderStep.selector,
                    popover: {
                        title: flow.goToCreateOrderStep.title,
                        description: flow.goToCreateOrderStep.description,
                        showButtons: ['next', 'previous', 'close'],
                        nextBtnText: 'Próximo',
                        onNextClick: () => {
                            localStorage.setItem(ORDER_PHASE_KEY, 'awaiting-create');
                            window.location.href = flow.createStep.url;
                        },
                    },
                },
            ],
            onCloseClick: bailAndClose,
        }).drive();

        return;
    }

    if (phase === 'awaiting-create') {
        if (pathOf(flow.createStep.url) !== currentPath) {
            window.location.href = flow.createStep.url;

            return;
        }

        // Detalha cada campo (Código da peça, Quantidade, Fabricante) com Próximo/Voltar
        // normais antes de chegar no botão de enviar, que fica sem botão de avançar —
        // só o evento order-created de verdade leva pro próximo passo dali. O campo de
        // fabricantes usa um elemento proxy (ver syncManufacturersProxyRect) em vez do
        // seletor direto, pra poder alargar o recorte do overlay quando o dropdown abre.
        const fieldSteps = flow.createFieldSteps.map((step) => {
            const isManufacturers = step.selector === '.onboarding-target-manufacturers';

            return {
                element: isManufacturers
                    ? () => {
                        syncManufacturersProxyRect();

                        return manufacturersProxyElement();
                    }
                    : step.selector,
                popover: {
                    title: step.title,
                    description: step.description,
                    ...(step.side ? { side: step.side } : {}),
                    ...(step.align ? { align: step.align } : {}),
                },
                ...(isManufacturers ? { onDeselected: teardownManufacturersProxy } : {}),
            };
        });

        const driverObj = driver({
            showProgress: false,
            allowClose: true,
            overlayClickBehavior: () => {},
            // Só o X fecha o tour (ver onCloseClick/bailAndClose) — Escape não fecha mais.
            allowKeyboardControl: false,
            skipMissingElement: true,
            nextBtnText: 'Próximo',
            prevBtnText: 'Voltar',
            steps: [
                ...fieldSteps,
                {
                    element: flow.createStep.selector,
                    popover: {
                        title: flow.createStep.title,
                        description: flow.createStep.description,
                        showButtons: ['close'],
                        // O botão de enviar fica perto do fim da página — sem forçar o
                        // popover pra cima dele, o driver.js às vezes o posiciona em
                        // cima do próprio botão, bloqueando o clique nele por baixo.
                        side: 'top',
                        align: 'center',
                    },
                },
            ],
            onCloseClick: (element, step, opts) => {
                manufacturersObserver?.disconnect();
                teardownManufacturersProxy();
                bailAndClose(element, step, opts);
            },
        });

        driverObj.drive();

        // Observa o wrapper do campo de fabricantes pra alargar o recorte do overlay
        // (via o proxy) sempre que o dropdown dele abrir/fechar.
        const manufacturersWrapper = document.querySelector('.onboarding-target-manufacturers');
        const manufacturersObserver = manufacturersWrapper
            ? new MutationObserver(() => {
                syncManufacturersProxyRect();
                driverObj.refresh();
            })
            : null;
        manufacturersObserver?.observe(manufacturersWrapper, { childList: true, subtree: true });

        window.Livewire.on('order-created', ({ orderId }) => {
            manufacturersObserver?.disconnect();
            localStorage.setItem(ORDER_ID_KEY, String(orderId));
            localStorage.setItem(ORDER_PHASE_KEY, 'verifying');
            window.location.href = flow.urls.history;
        });

        return;
    }

    if (phase === 'verifying' || phase === 'awaiting-cancel') {
        if (pathOf(flow.urls.history) !== currentPath) {
            window.location.href = flow.urls.history;

            return;
        }

        const orderId = localStorage.getItem(ORDER_ID_KEY);

        if (!orderId) {
            bail();

            return;
        }

        if (phase === 'verifying') {
            localStorage.setItem(ORDER_PHASE_KEY, 'awaiting-cancel');
        }

        const driverObj = driver({
            showProgress: false,
            allowClose: true,
            overlayClickBehavior: () => {},
            // Só o X fecha o tour (ver onCloseClick/bailAndClose) — Escape não fecha mais.
            allowKeyboardControl: false,
            skipMissingElement: true,
            nextBtnText: 'Próximo',
            prevBtnText: 'Voltar',
            steps: [
                {
                    element: `[data-order-row="${orderId}"] .mo-order-status`,
                    popover: {
                        title: flow.verifyStep.title,
                        description: flow.verifyStep.description,
                    },
                },
                {
                    element: `[data-order-row="${orderId}"] .mo-cancel-btn`,
                    popover: {
                        title: flow.cancelStep.title,
                        description: flow.cancelStep.description,
                        showButtons: ['close'],
                        side: 'top',
                        align: 'center',
                    },
                },
            ],
            onCloseClick: bailAndClose,
        });

        driverObj.drive(phase === 'awaiting-cancel' ? 1 : 0);

        window.Livewire.on('order-cancelled', () => {
            driverObj.destroy();
            localStorage.setItem(ORDER_PHASE_KEY, 'done');

            driver({
                showProgress: false,
                allowClose: true,
                overlayClickBehavior: () => {},
                // Só o X fecha o tour (ver onCloseClick/bailAndClose) — Escape não fecha mais.
                allowKeyboardControl: false,
                steps: [{
                    popover: {
                        title: flow.doneStep.title,
                        description: flow.doneStep.description,
                        showButtons: ['next', 'close'],
                        doneBtnText: 'Concluir',
                    },
                }],
                onDestroyed: bail,
            }).drive();
        });

        return;
    }
}

function startTour(steps, finish) {
    if (steps.type === 'guided-order') {
        startGuidedOrderTour(steps, finish);

        return;
    }

    startLinearTour(steps.steps, finish);
}

window.addEventListener('onboarding-tour:ready', (event) => {
    const { needsTutorial, steps, finish } = event.detail;

    if (needsTutorial) {
        startTour(steps, finish);

        return;
    }

    // Um reinício manual (ver App\Livewire\HelpMenu) pode ter precisado navegar pra outra
    // página no meio do caminho (ver os window.location.href em startLinearTour/
    // startGuidedOrderTour) — nesse caso needsTutorial vem false (o usuário já concluiu o
    // tutorial antes), mas o localStorage ainda marca que um tour está em andamento.
    if (hasActiveTourState()) {
        startTour(steps, finish);

        return;
    }

    clearState();
});

// Disparado pelo botão "?" no topbar (ver App\Livewire\HelpMenu e
// App\Livewire\OnboardingTutorial::restart()) — o usuário pode pedir pra rever o tutorial
// a qualquer momento, não só no primeiro acesso. Limpa qualquer fase/pedido de um tour
// anterior que tenha ficado pela metade antes de começar de novo do zero.
window.Livewire.on('onboarding-tour:restart', ({ steps }) => {
    clearState();
    startTour(steps, window.onboardingTourFinish);
});
