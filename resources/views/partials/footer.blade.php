<?php

use App\Models\Setting;

    $socialFacebook = Setting::get('social_facebook');
$socialInstagram = Setting::get('social_instagram');
$socialLinkedin = Setting::get('social_linkedin');
$socialYoutube = Setting::get('social_youtube');
$contactPhone = Setting::get('contact_phone');
$contactEmail = Setting::get('contact_email');
$contactAddress = Setting::get('contact_address');
$hasSocials = filled($socialFacebook) || filled($socialInstagram) || filled($socialLinkedin) || filled($socialYoutube);
$hasContact = filled($contactPhone) || filled($contactEmail) || filled($contactAddress);
?>
<footer class="footer">
    <div class="container">
        <div class="footer__top">
            <div class="footer__brand">
                <a href="{{ route('home') }}" class="footer__logo">
                    <img src="{{ asset('images/white-logo.png') }}" alt="Auto Peças Center">
                </a>

                <p class="footer__description">
                    O sistema que conecta sua empresa a todos os catálogos de autopeças e encontra as melhores
                    condições para você e seu cliente.
                </p>

                @if ($hasSocials)
                    <div class="footer__socials">
                        @if (filled($socialFacebook))
                            <a href="{{ $socialFacebook }}" class="footer__social" aria-label="Facebook">
                                <svg viewBox="0 0 640 640" fill="currentColor">
                                    <path
                                        d="M240 363.3L240 576L356 576L356 363.3L442.5 363.3L460.5 265.5L356 265.5L356 230.9C356 179.2 376.3 159.4 428.7 159.4C445 159.4 458.1 159.8 465.7 160.6L465.7 71.9C451.4 68 416.4 64 396.2 64C289.3 64 240 114.5 240 223.4L240 265.5L174 265.5L174 363.3L240 363.3z" />
                                </svg>
                            </a>
                        @endif
                        @if (filled($socialInstagram))
                            <a href="{{ $socialInstagram }}" class="footer__social" aria-label="Instagram">
                                <svg viewBox="0 0 640 640" fill="currentColor">
                                    <path
                                        d="M320.3 205C256.8 204.8 205.2 256.2 205 319.7C204.8 383.2 256.2 434.8 319.7 435C383.2 435.2 434.8 383.8 435 320.3C435.2 256.8 383.8 205.2 320.3 205zM319.7 245.4C360.9 245.2 394.4 278.5 394.6 319.7C394.8 360.9 361.5 394.4 320.3 394.6C279.1 394.8 245.6 361.5 245.4 320.3C245.2 279.1 278.5 245.6 319.7 245.4zM413.1 200.3C413.1 185.5 425.1 173.5 439.9 173.5C454.7 173.5 466.7 185.5 466.7 200.3C466.7 215.1 454.7 227.1 439.9 227.1C425.1 227.1 413.1 215.1 413.1 200.3zM542.8 227.5C541.1 191.6 532.9 159.8 506.6 133.6C480.4 107.4 448.6 99.2 412.7 97.4C375.7 95.3 264.8 95.3 227.8 97.4C192 99.1 160.2 107.3 133.9 133.5C107.6 159.7 99.5 191.5 97.7 227.4C95.6 264.4 95.6 375.3 97.7 412.3C99.4 448.2 107.6 480 133.9 506.2C160.2 532.4 191.9 540.6 227.8 542.4C264.8 544.5 375.7 544.5 412.7 542.4C448.6 540.7 480.4 532.5 506.6 506.2C532.8 480 541 448.2 542.8 412.3C544.9 375.3 544.9 264.5 542.8 227.5zM495 452C487.2 471.6 472.1 486.7 452.4 494.6C422.9 506.3 352.9 503.6 320.3 503.6C287.7 503.6 217.6 506.2 188.2 494.6C168.6 486.8 153.5 471.7 145.6 452C133.9 422.5 136.6 352.5 136.6 319.9C136.6 287.3 134 217.2 145.6 187.8C153.4 168.2 168.5 153.1 188.2 145.2C217.7 133.5 287.7 136.2 320.3 136.2C352.9 136.2 423 133.6 452.4 145.2C472 153 487.1 168.1 495 187.8C506.7 217.3 504 287.3 504 319.9C504 352.5 506.7 422.6 495 452z" />
                                </svg>
                            </a>
                        @endif
                        @if (filled($socialLinkedin))
                            <a href="{{ $socialLinkedin }}" class="footer__social" aria-label="LinkedIn">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="12" r="10.5" stroke="currentColor" stroke-width="1.3" />
                                    <rect x="7.2" y="10" width="1.8" height="6.5" fill="currentColor" />
                                    <circle cx="8.1" cy="7.6" r="1.05" fill="currentColor" />
                                    <path
                                        d="M10.6 10h1.7v1c.4-.7 1.1-1.2 2.2-1.2 1.9 0 2.7 1.2 2.7 3.1v3.6h-1.8v-3.2c0-.9-.3-1.6-1.3-1.6-.9 0-1.4.6-1.4 1.6v3.2h-1.8V10z"
                                        fill="currentColor" />
                                </svg>
                            </a>
                        @endif
                        @if (filled($socialYoutube))
                            <a href="{{ $socialYoutube }}" class="footer__social" aria-label="YouTube">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="12" r="10.5" stroke="currentColor" stroke-width="1.3" />
                                    <path d="M10.2 9.2 15 12l-4.8 2.8z" fill="currentColor" />
                                </svg>
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            <div class="footer__column">
                <h3 class="footer__heading">Navegação</h3>
                <ul class="footer__links">
                    <li><a href="{{ route('home') }}">Home</a></li>
                    <li><a href="{{ route('como-funciona') }}">Como funciona</a></li>
                    <li><a href="{{ route('planos') }}">Planos</a></li>
                    <li><a href="{{ route('contato') }}">Contato</a></li>
                </ul>
            </div>

            <div class="footer__column">
                <h3 class="footer__heading">Para seu negócio</h3>
                <ul class="footer__links">
                    <li><a href="{{ route('planos') }}">Planos e preços</a></li>
                    <li><a href="#">Segurança</a></li>
                    <li><a href="{{ route('contato') }}">Suporte</a></li>
                    <li><a href="{{ route('perguntas-frequentes') }}">Perguntas frequentes</a></li>
                </ul>
            </div>

            @if ($hasContact)
                <div class="footer__column">
                    <h3 class="footer__heading">Contato</h3>
                    <ul class="footer__contact">
                        @if (filled($contactPhone))
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path
                                        d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.850.573 2.81.7A2 2 0 0 1 22 16.92z" />
                                </svg>
                                <span>{{ $contactPhone }}</span>
                            </li>
                        @endif
                        @if (filled($contactEmail))
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                    <polyline points="22 6 12 13 2 6" />
                                </svg>
                                <span>{{ $contactEmail }}</span>
                            </li>
                        @endif
                        @if (filled($contactAddress))
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                <span>{{ $contactAddress }}</span>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif
        </div>

        <div class="footer__bottom">
            <p>&copy; {{ date('Y') }} Auto Peças Center. Todos os direitos reservados.</p>
        </div>
    </div>
</footer>
