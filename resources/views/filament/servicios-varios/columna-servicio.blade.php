@php $servicio = $getRecord(); @endphp
<div class="flex items-start gap-3 px-3 py-2">
    <x-tipo-chip :tipo="\App\Enums\TipoLinea::Vario" />
    <div class="min-w-0">
        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $servicio->nombre }}</p>
        @if ($servicio->descripcion)
            <p class="max-w-md text-xs text-gray-500 dark:text-gray-400">{{ $servicio->descripcion }}</p>
        @endif
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $servicio->moneda }}@if ($servicio->lineas_en_uso_count) · en {{ $servicio->lineas_en_uso_count }} contrato(s)@endif</p>
    </div>
</div>
