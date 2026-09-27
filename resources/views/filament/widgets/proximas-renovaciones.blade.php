@php use App\Support\Formato as F; @endphp
<x-filament-widgets::widget>
    <x-filament::section heading="Próximas renovaciones">
        <x-slot name="afterHeader">
            <x-filament::link :href="$urlContratos" size="sm">Ver contratos →</x-filament::link>
        </x-slot>

        @forelse ($lineas as $l)
            @php $alerta = $l->alerta(); @endphp
            <a href="{{ \App\Filament\Resources\Contratos\ContratoResource::urlVer($l->contrato_id) }}"
               class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5 hover:bg-gray-50 dark:hover:bg-white/5"
               aria-label="Abrir {{ $l->contrato->numero }}">
                <x-tipo-chip :tipo="$l->tipo" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $l->etiqueta }}</p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ $l->contrato->cliente->razon_social }} · {{ $l->nombre }} · {{ mb_strtolower($l->periodo->getLabel()) }}
                    </p>
                </div>
                @if ($verMontos)
                    <div class="hidden text-right sm:block">
                        <p class="text-sm tabular-nums text-gray-950 dark:text-white">{{ F::pen($l->subtotal()['pen']) }}</p>
                        <p class="text-xs text-gray-500">IGV incluido</p>
                    </div>
                @endif
                <div class="w-28 text-right">
                    <p class="text-sm tabular-nums text-gray-950 dark:text-white">{{ F::fecha($l->vence_el) }}</p>
                    <p @class([
                        'text-xs',
                        'font-medium text-danger-600 dark:text-danger-400' => $alerta === 'vencido',
                        'font-medium text-warning-600 dark:text-warning-400' => $alerta === 'por_vencer',
                        'text-gray-500 dark:text-gray-400' => $alerta === 'ok',
                    ])>{{ F::rel($l->vence_el) }}</p>
                </div>
                <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400" />
            </a>
        @empty
            <div class="flex flex-col items-center gap-2 py-8 text-sm text-gray-500">
                <x-filament::icon icon="heroicon-o-calendar-days" class="h-8 w-8 text-gray-400" />
                Sin renovaciones próximas
            </div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
