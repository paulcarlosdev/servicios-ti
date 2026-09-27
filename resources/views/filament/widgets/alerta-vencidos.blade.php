<x-filament-widgets::widget>
    <div class="flex flex-wrap items-center gap-3 rounded-xl bg-danger-50 p-4 text-sm text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30">
        <x-filament::icon icon="heroicon-o-exclamation-circle" class="h-5 w-5 shrink-0" />
        <p class="flex-1">
            <strong>{{ $vencidas->count() }} {{ $vencidas->count() === 1 ? 'servicio vencido' : 'servicios vencidos' }}</strong>
            sin renovar:
            {{ $vencidas->map(fn ($l) => $l->tipo->getLabel().' '.$l->etiqueta)->join(', ') }}.
        </p>
        <x-filament::button color="danger" size="sm" tag="a" :href="$url">Revisar</x-filament::button>
    </div>
</x-filament-widgets::widget>
