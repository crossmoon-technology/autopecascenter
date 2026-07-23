<section class="page-hero">
    <div class="container">
        <h1 class="page-hero__title">{{ $title }}</h1>
        @isset($subtitle)
            <p class="page-hero__subtitle">{{ $subtitle }}</p>
        @endisset
    </div>
</section>
