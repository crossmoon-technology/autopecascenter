@php
    $informativo = $getRecord();
    $url = \Illuminate\Support\Facades\Storage::disk('public')->url($informativo->file);
    $isImage = $informativo->type === \App\Models\Informativo\Enums\InformativoType::Imagem;
@endphp

<div style="display: flex; align-items: center; justify-content: center;">
    @if ($isImage)
        <img
            src="{{ $url }}"
            alt=""
            style="width: 48px; height: 48px; object-fit: cover; border-radius: 0.375rem; border: 1px solid rgba(127, 127, 127, 0.25);"
        >
    @else
        <div
            style="width: 48px; height: 48px; overflow: hidden; display: flex; align-items: flex-start; justify-content: center; border-radius: 0.375rem; background: #fff;"
        >
            <canvas
                width="96"
                height="96"
                style="display: block; width: 100%; height: 100%; object-fit: cover; object-position: top;"
                data-pdf-preview-url="{{ $url }}"
            ></canvas>
        </div>
    @endif
</div>
