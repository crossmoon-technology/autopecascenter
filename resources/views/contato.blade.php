@extends('layouts.app')

@section('content')
    <main>
        @include('partials.page-hero', [
            'title' => 'Fale com a gente',
            'subtitle' => 'Tire suas dúvidas, ou inicie uma avaliação grátis.',
        ])

        <section class="contact">
            <div class="container contact__inner">
                <form class="contact__form">
                    <div class="contact__row">
                        <div class="contact__field">
                            <label for="name">Nome *</label>
                            <input id="name" type="text" name="name" placeholder="Seu nome completo" required>
                        </div>
                        <div class="contact__field">
                            <label for="email">E-mail *</label>
                            <input id="email" type="email" name="email" placeholder="seu@email.com" required>
                        </div>
                    </div>

                    <div class="contact__row">
                        <div class="contact__field">
                            <label for="phone">Telefone *</label>
                            <input id="phone" type="tel" name="phone" placeholder="(11) 99999-9999" required>
                        </div>
                        <div class="contact__field">
                            <label for="company">Empresa</label>
                            <input id="company" type="text" name="company" placeholder="Nome da sua empresa">
                        </div>
                    </div>

                    <div class="contact__field">
                        <label for="message">Mensagem</label>
                        <textarea id="message" name="message" rows="5" placeholder="Como podemos ajudar?" required></textarea>
                    </div>

                    <button type="submit" class="btn btn--solid btn--block">Enviar mensagem</button>
                </form>

                <div class="contact__info">
                    <div class="contact__info-item">
                        <span class="contact__info-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.850.573 2.81.7A2 2 0 0 1 22 16.92z" />
                            </svg>
                        </span>
                        <div>
                            <h3>Telefone</h3>
                            <p>(11) 99999-9999</p>
                        </div>
                    </div>

                    <div class="contact__info-item">
                        <span class="contact__info-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                <polyline points="22 6 12 13 2 6" />
                            </svg>
                        </span>
                        <div>
                            <h3>E-mail</h3>
                            <p>contato@autopecascenter.com.br</p>
                        </div>
                    </div>

                    <div class="contact__info-item">
                        <span class="contact__info-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                <circle cx="12" cy="10" r="3" />
                            </svg>
                        </span>
                        <div>
                            <h3>Endereço</h3>
                            <p>Av. das Peças, 123 - São Paulo/SP<br>CEP: 01234-567</p>
                        </div>
                    </div>

                    <div class="contact__info-item">
                        <span class="contact__info-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                        </span>
                        <div>
                            <h3>Horário de atendimento</h3>
                            <p>Segunda a sexta, 8h às 18h</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
@endsection
