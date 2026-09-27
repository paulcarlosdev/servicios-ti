@php
    $porTipo = $getRecord()->lineasVigentes->groupBy(fn ($l) => $l->tipo->value);
@endphp
<div class="flex flex-wrap gap-1.5 px-3 py-2">
    @foreach (\App\Enums\TipoLinea::cases() as $tipo)
        @if ($porTipo->has($tipo->value))
            <span class="inline-flex items-center gap-1 rounded-md bg-gray-50 px-1.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10" title="{{ $tipo->getLabel() }}">
                <x-filament::icon :icon="$tipo->getIcon()" class="h-3.5 w-3.5 text-gray-400" />
                {{ $porTipo[$tipo->value]->count() }}
            </span>
        @endif
    @endforeach
    @if ($porTipo->isEmpty())
        <span class="text-xs text-gray-400">Sin servicios</span>
    @endif
</div>
