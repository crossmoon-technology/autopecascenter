<div>
    <style>
        .help-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 9999px;
            background: transparent;
            border: none;
            cursor: pointer;
            color: inherit;
        }
        .help-trigger:hover {
            background: rgba(127, 127, 127, 0.1);
        }
        .help-trigger svg {
            width: 1.375rem;
            height: 1.375rem;
        }
    </style>

    <x-filament::dropdown placement="bottom-end">
        <x-slot name="trigger">
            <button type="button" class="help-trigger" title="Ajuda">
                <x-filament::icon icon="heroicon-o-question-mark-circle" />
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item
                icon="heroicon-o-play-circle"
                wire:click="startTutorial"
            >
                Iniciar tutorial
            </x-filament::dropdown.list.item>

            <x-filament::dropdown.list.item
                icon="heroicon-o-question-mark-circle"
                tag="a"
                :href="$faqUrl"
            >
                Perguntas frequentes
            </x-filament::dropdown.list.item>
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</div>
