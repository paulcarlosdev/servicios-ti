@php $plan = $getRecord(); @endphp
<div class="flex items-start gap-3 px-3 py-2">
    <x-tipo-chip :tipo="$plan->tipo" />
    <div class="min-w-0">
        <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-gray-950 dark:text-white">
            {{ $plan->nombre }}
            @if ($plan->destacado)
                <x-filament::badge size="sm" icon="heroicon-m-star">Popular</x-filament::badge>
            @endif
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $plan->detalleLinea() }}</p>
        @if ($plan->extra)
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $plan->extra }}</p>
        @endif
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $plan->moneda }}@if ($plan->uso) · {{ (int) $plan->uso }} en contratos @endif</p>
    </div>
</div>
