{{-- Vista previa de la cabecera del PDF con los datos de la ficha --}}
<div class="rounded-lg bg-white p-4 text-[11px] leading-snug text-gray-700 shadow-sm ring-1 ring-gray-950/10">
    <div class="flex items-start gap-3 border-b-2 border-gray-900 pb-3">
        @if ($logo)
            <img src="{{ $logo }}" alt="Logo" class="h-12 w-12 object-contain">
        @else
            <div class="flex h-12 w-12 items-center justify-center bg-gray-900 text-base font-bold text-white">TI</div>
        @endif
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-bold text-gray-900">{{ $razon ?: 'Razón social' }}</p>
            <p>RUC {{ $ruc ?: '—' }}</p>
            <p class="truncate text-gray-500">{{ $direccion }}</p>
            <p class="truncate text-gray-500">{{ $contacto }}</p>
        </div>
        <div class="text-right">
            <p class="text-[9px] uppercase tracking-wider text-gray-500">Cotización</p>
            <p class="text-sm font-bold text-gray-900">COT-EJEMPLO</p>
        </div>
    </div>
    <div class="mt-3 space-y-1.5">
        <div class="h-2 w-3/4 rounded bg-gray-100"></div>
        <div class="h-2 w-1/2 rounded bg-gray-100"></div>
        <div class="h-2 w-2/3 rounded bg-gray-100"></div>
    </div>
</div>
