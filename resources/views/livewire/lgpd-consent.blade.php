<div>
    @if ($visible)
    <div x-data="{ agreed: false }">
        <style>
            .lgpd-overlay {
                position: fixed;
                inset: 0;
                z-index: 2000000000;
                background: rgba(0, 0, 0, 0.75);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
            }
            .lgpd-card {
                width: 100%;
                max-width: 34rem;
                max-height: calc(100vh - 3rem);
                display: flex;
                flex-direction: column;
                background: #fff;
                color: #1f2328;
                border-radius: 0.75rem;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.35);
                overflow: hidden;
            }
            .lgpd-card-header {
                padding: 1.5rem 1.5rem 0;
            }
            .lgpd-card-header h2 {
                font-size: 1.25rem;
                font-weight: 700;
                margin: 0 0 0.5rem;
            }
            .lgpd-card-header p {
                font-size: 0.875rem;
                opacity: 0.75;
                margin: 0;
            }
            .lgpd-scroll {
                margin: 1rem 1.5rem;
                padding: 1rem;
                border: 1px solid rgba(127, 127, 127, 0.25);
                border-radius: 0.5rem;
                overflow-y: auto;
                font-size: 0.8125rem;
                line-height: 1.55;
            }
            .lgpd-scroll h3 {
                font-size: 0.875rem;
                font-weight: 700;
                margin: 0 0 0.375rem;
            }
            .lgpd-scroll h3:not(:first-child) {
                margin-top: 0.875rem;
            }
            .lgpd-scroll p {
                margin: 0 0 0.5rem;
            }
            .lgpd-scroll ul {
                margin: 0 0 0.5rem;
                padding-left: 1.125rem;
            }
            .lgpd-footer {
                padding: 0 1.5rem 1.5rem;
                display: flex;
                flex-direction: column;
                gap: 0.875rem;
            }
            .lgpd-checkbox {
                display: flex;
                align-items: flex-start;
                gap: 0.5rem;
                font-size: 0.8125rem;
                line-height: 1.4;
                cursor: pointer;
            }
            .lgpd-checkbox input {
                margin-top: 0.1875rem;
                flex-shrink: 0;
            }
            .lgpd-accept-btn {
                align-self: flex-end;
                background: #F94603;
                color: #fff;
                border: none;
                border-radius: 0.5rem;
                padding: 0.625rem 1.25rem;
                font-size: 0.875rem;
                font-weight: 600;
                cursor: pointer;
            }
            .lgpd-accept-btn:disabled {
                opacity: 0.45;
                cursor: not-allowed;
            }
        </style>

        <div class="lgpd-overlay">
            <div class="lgpd-card" role="dialog" aria-modal="true" aria-labelledby="lgpd-title">
                <div class="lgpd-card-header">
                    <h2 id="lgpd-title">Bem-vindo(a) à Auto Peças Center!</h2>
                    <p>Antes de começar, precisamos da sua confirmação sobre como cuidamos dos seus dados por aqui.</p>
                </div>

                <div class="lgpd-scroll">
                    <h3>Cookies</h3>
                    <p>Usamos cookies essenciais pra manter você conectado(a) e lembrar suas preferências enquanto navega no painel. Eles são necessários pro funcionamento da plataforma — não usamos cookies de rastreamento ou publicidade de terceiros.</p>

                    <h3>Seus dados pessoais</h3>
                    <p>Guardamos as informações necessárias pra operar sua conta e seus pedidos, como nome, e-mail, documento (CPF/CNPJ) e o histórico de buscas e pedidos feitos por você na plataforma. Esses dados são usados só pra viabilizar o serviço — cotações, pedidos, atendimento e comunicação sobre sua conta.</p>

                    <h3>Seus direitos (LGPD)</h3>
                    <p>De acordo com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018), você pode a qualquer momento solicitar:</p>
                    <ul>
                        <li>Acesso aos dados que temos sobre você;</li>
                        <li>Correção de dados incompletos ou desatualizados;</li>
                        <li>Exclusão dos seus dados, quando aplicável.</li>
                    </ul>
                    <p>Basta entrar em contato com quem administra sua conta na plataforma.</p>
                </div>

                <div class="lgpd-footer">
                    <label class="lgpd-checkbox">
                        <input type="checkbox" x-model="agreed">
                        Li e aceito o uso de cookies essenciais e o tratamento dos meus dados pessoais conforme descrito acima.
                    </label>

                    <button
                        type="button"
                        class="lgpd-accept-btn"
                        x-bind:disabled="!agreed"
                        wire:click="accept"
                    >
                        Aceitar e continuar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
