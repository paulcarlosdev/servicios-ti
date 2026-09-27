@php $cotizacion = $getRecord(); @endphp
<div class="flex items-center gap-3 px-3 py-2">
    <x-iniciales :nombre="$cotizacion->cliente->razon_social" />
    <div class="min-w-0">
        <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $cotizacion->cliente->razon_social }}</p>
        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $cotizacion->numero }} · {{ \App\Support\Formato::fecha($cotizacion->fecha) }}</p>
    </div>
</div>
