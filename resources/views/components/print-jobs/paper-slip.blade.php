@props([
    'document',
    'label' => null,
])

{{--
    Stellt ein RenderedPrint so dar, wie es aus dem Bondrucker kommt:
    32 Zeichen breit, Titel doppelt groß und zentriert, danach die
    Zeilen linksbündig in Monospace.
--}}
<div class="flex flex-col items-center gap-2">
    <div class="drop-shadow-[0_6px_14px_rgba(0,0,0,0.35)]">
        <div class="bg-[#fbfaf5] px-5 pb-4 pt-5 font-mono text-[12.5px] leading-[1.45] text-[#1b1a17]">
            <div class="w-[32ch]">
                <div class="whitespace-pre-wrap break-words text-center text-[2em] font-bold leading-tight">{{ $document->title }}</div>

                @if($document->headerLines !== [])
                    <div class="mt-1 text-center">
                        @foreach($document->headerLines as $line)
                            <div class="min-h-[1.45em] whitespace-pre-wrap break-words">{{ $line }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-[1.45em]">
                    @foreach($document->lines as $line)
                        <div class="min-h-[1.45em] whitespace-pre-wrap break-words">{{ $line }}</div>
                    @endforeach
                </div>

                @if($document->openDrawer)
                    <div class="mt-3 text-center text-[10px] uppercase tracking-widest text-[#8a8577]">[ Kassenlade öffnen ]</div>
                @endif
            </div>
        </div>

        {{-- Abrisskante --}}
        @if($document->cutPaper)
            <div
                class="h-2"
                style="background:
                    linear-gradient(-45deg, transparent 5px, #fbfaf5 0) 0 0 / 10px 100% repeat-x,
                    linear-gradient(45deg, transparent 5px, #fbfaf5 0) 0 0 / 10px 100% repeat-x;"
            ></div>
        @endif
    </div>

    @if($label)
        <div class="text-[11px] uppercase tracking-wide text-dim">{{ $label }}</div>
    @endif
</div>
