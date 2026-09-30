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
    <x-print-jobs.paper :cut-paper="$document->cutPaper" class="text-[12.5px]">
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
                @php $formatted = $line instanceof \App\Printing\PrintLine; @endphp
                <div @class([
                    'min-h-[1.45em] whitespace-pre-wrap break-words',
                    'font-bold' => $formatted && $line->bold,
                    'text-center' => $formatted && $line->center,
                ])>{{ $line }}</div>
            @endforeach
        </div>

        @if($document->openDrawer)
            <div class="mt-3 text-center text-[10px] uppercase tracking-widest text-[#8a8577]">[ Kassenlade öffnen ]</div>
        @endif
    </x-print-jobs.paper>

    @if($label)
        <div class="text-[11px] uppercase tracking-wide text-dim">{{ $label }}</div>
    @endif
</div>
