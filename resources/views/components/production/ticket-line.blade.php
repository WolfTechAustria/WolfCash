@props([
    'done' => false,
    'complete',
    'reopen',
    'note' => null,
])

{{--
    Eine antippbare Bonzeile: offen „[ ]“, fertig „[x]“ und
    durchgestrichen. Erneutes Tippen nimmt die Markierung zurück.
--}}
<button
    type="button"
    wire:click="{{ $done ? $reopen : $complete }}"
    wire:loading.attr="disabled"
    title="{{ $done ? 'Fertig-Markierung zurücknehmen' : 'Als fertig markieren' }}"
    {{ $attributes->class([
        '-mx-2 flex min-h-[3rem] w-[calc(100%+1rem)] items-start gap-[1ch] rounded px-2 py-1.5 text-left transition',
        'hover:bg-black/[0.04] active:bg-black/[0.08]',
        'text-[#1b1a17]/40' => $done,
    ]) }}
>
    <span class="shrink-0 font-bold {{ $done ? 'text-[#1f8a4c]' : '' }}">{{ $done ? '[x]' : '[ ]' }}</span>

    <span class="min-w-0 flex-1">
        <span @class(['block whitespace-pre-wrap break-words font-bold', 'line-through' => $done])>{{ $slot }}</span>

        @if($note)
            <span class="block whitespace-pre-wrap break-words {{ $done ? '' : 'font-bold text-[#b4471c]' }}">&gt; {{ $note }}</span>
        @endif
    </span>
</button>
