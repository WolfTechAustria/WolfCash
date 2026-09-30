@props([
    'cutPaper' => true,
])

{{--
    Bonpapier: 32 Zeichen breit in Monospace, mit Abrisskante.
    Schriftgröße über die Klasse steuern, alles skaliert mit.
--}}
<div {{ $attributes->class('drop-shadow-[0_6px_14px_rgba(0,0,0,0.35)]') }}>
    <div class="bg-[#fbfaf5] px-5 pb-4 pt-5 font-mono leading-[1.45] text-[#1b1a17]">
        <div class="w-[32ch] max-w-full">
            {{ $slot }}
        </div>
    </div>

    {{-- Abrisskante --}}
    @if($cutPaper)
        <div
            class="h-2"
            style="background:
                linear-gradient(-45deg, transparent 5px, #fbfaf5 0) 0 0 / 10px 100% repeat-x,
                linear-gradient(45deg, transparent 5px, #fbfaf5 0) 0 0 / 10px 100% repeat-x;"
        ></div>
    @endif
</div>
