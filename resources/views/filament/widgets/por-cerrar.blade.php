@php use App\Support\Formato as F; @endphp
<x-filament-widgets::widget>
    <x-filament::section heading="Por cerrar">
        <x-slot name="afterHeader">
            <x-filament::link :href="$urlCotizaciones" size="sm">Cotizaciones</x-filament::link>
        </x-slot>

        <div class="space-y-1">
            @foreach ($cotizaciones as $c)
                <a href="{{ \App\Filament\Resources\Cotizaciones\CotizacionResource::urlVer($c->id) }}" class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-gray-50 dark:hover:bg-white/5">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-warning-50 text-warning-600 dark:bg-warning-400/10 dark:text-warning-400">
                        <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $c->cliente->razon_social }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $c->numero }} · vence {{ F::rel($c->valida_hasta) }}</p>
                    </div>
                    @if ($verMontos)
                        <span class="text-sm tabular-nums text-gray-950 dark:text-white">{{ F::pen($c->total_pen) }}</span>
                    @endif
                </a>
            @endforeach

            @foreach ($borradores as $c)
                <a href="{{ \App\Filament\Resources\Contratos\ContratoResource::urlVer($c->id) }}" class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-gray-50 dark:hover:bg-white/5">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $c->cliente->razon_social }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $c->numero }} · contrato en borrador</p>
                    </div>
                </a>
            @endforeach

            @if ($cotizaciones->isEmpty() && $borradores->isEmpty())
                <p class="py-6 text-center text-sm text-gray-500">Nada pendiente.</p>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
