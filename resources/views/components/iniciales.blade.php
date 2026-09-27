@props(['nombre', 'size' => 'md'])
{{-- Avatar con las iniciales del nombre (sin servicios externos) --}}
@php
    $iniciales = collect(preg_split('/\s+/', trim($nombre)))
        ->filter(fn ($p) => preg_match('/^\p{L}/u', $p))
        ->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->join('');
    $caja = $size === 'lg' ? 'h-12 w-12 text-base' : 'h-9 w-9 text-xs';
@endphp
<span {{ $attributes->class(["inline-flex shrink-0 items-center justify-center rounded-full bg-gray-100 font-semibold text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10 {$caja}"]) }} aria-hidden="true">{{ $iniciales ?: '?' }}</span>
