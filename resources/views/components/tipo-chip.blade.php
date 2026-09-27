@props(['tipo', 'size' => 'md'])
{{-- Ícono del tipo de línea en su color (dominio sky, VPS violet, hosting emerald, vario amber) --}}
@php
    $tonos = [
        'sky' => 'bg-sky-50 text-sky-600 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-400',
        'violet' => 'bg-violet-50 text-violet-600 ring-violet-600/20 dark:bg-violet-400/10 dark:text-violet-400',
        'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-400',
        'amber' => 'bg-amber-50 text-amber-600 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-400',
    ];
    $caja = $size === 'sm' ? 'h-7 w-7' : 'h-9 w-9';
@endphp
<span {{ $attributes->class(["inline-flex shrink-0 items-center justify-center rounded-lg ring-1 ring-inset {$caja}", $tonos[$tipo->getColor()]]) }} title="{{ $tipo->getLabel() }}">
    <x-filament::icon :icon="$tipo->getIcon()" class="h-4 w-4" />
</span>
