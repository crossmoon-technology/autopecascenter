<x-filament-panels::page>
    <style>
        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .faq-item {
            border: 1px solid rgba(127, 127, 127, 0.2);
            border-radius: 0.75rem;
            padding: 0;
            overflow: hidden;
        }
        .faq-question {
            cursor: pointer;
            list-style: none;
            font-weight: 600;
            font-size: 0.9375rem;
            padding: 1rem 1.25rem;
        }
        .faq-question::-webkit-details-marker {
            display: none;
        }
        .faq-question::before {
            content: '+';
            display: inline-block;
            width: 1rem;
            margin-right: 0.5rem;
            font-weight: 700;
            color: #F94603;
        }
        .faq-item[open] .faq-question::before {
            content: '−';
        }
        .faq-answer {
            padding: 0 1.25rem 1rem 2.75rem;
            font-size: 0.875rem;
            opacity: 0.8;
            line-height: 1.5;
        }
        .faq-empty {
            font-size: 0.875rem;
            opacity: 0.65;
        }
    </style>

    @php $faqItems = $this->getFaqItems(); @endphp

    @if (empty($faqItems))
        <p class="faq-empty">Nenhuma pergunta frequente cadastrada ainda.</p>
    @else
        <div class="faq-list">
            @foreach ($faqItems as $item)
                <details class="faq-item">
                    <summary class="faq-question">{{ $item['question'] }}</summary>
                    <div class="faq-answer">{{ $item['answer'] }}</div>
                </details>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
