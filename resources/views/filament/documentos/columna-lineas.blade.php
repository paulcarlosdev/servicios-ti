@php $lineas = $getRecord()->lineas; @endphp
<div class="flex flex-wrap items-center gap-1.5 px-3 py-2">
    @foreach ($lineas->take(2) as $l)
        <span class="inline-flex max-w-44 items-center gap-1 rounded-md bg-gray-50 px-1.5 py-0.5 text-xs text-gray-700 ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
            <x-filament::icon :icon="$l->tipo->getIcon()" class="h-3.5 w-3.5 shrink-0 text-gray-400" />
            <span class="truncate">{{ $l->etiqueta }}</span>
        </span>
    @endforeach
    @if ($lineas->count() > 2)
        <span class="text-xs text-gray-500">+{{ $lineas->count() - 2 }}</span>
    @endif
    @if ($lineas->isEmpty())
        <span class="text-xs text-gray-400">Sin servicios</span>
    @endif
</div>
