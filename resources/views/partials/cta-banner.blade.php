<section class="cta-banner">
    <div class="container cta-banner__inner">
        <div>
            <h2 class="cta-banner__title">{{ $title }}</h2>
            @isset($subtitle)
                <p class="cta-banner__subtitle">{{ $subtitle }}</p>
            @endisset
        </div>

        <a href="{{ $buttonUrl ?? '#agendar-demonstracao' }}" class="btn btn--solid">
            {{ $buttonLabel ?? 'Iniciar avaliação' }}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12" />
                <polyline points="12 5 19 12 12 19" />
            </svg>
        </a>
    </div>
</section>
