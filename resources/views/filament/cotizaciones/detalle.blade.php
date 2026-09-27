@php
    use App\Support\Formato as F;
    $c = $cotizacion;
    $estado = $c->estadoVisible();
    $t = $c->totales();
    $verMontos = auth()->user()->puedeVer('montos');
    $nivel = match ($estado->value) { 'aceptada', 'rechazada' => 1, 'convertida' => 2, default => 0 };
    $pasos = ['Enviada', $estado->value === 'rechazada' ? 'Rechazada' : 'Aceptada', 'Contrato'];
@endphp
<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <ol class="flex flex-1 items-center gap-2 text-sm">
            @foreach ($pasos as $i => $paso)
                @php
                    $hecho = $i <= $nivel;
                    $rojo = $i === 1 && $estado->value === 'rechazada';
                @endphp
                <li class="flex items-center gap-2">
                    <span @class([
                        'flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold',
                        'bg-danger-500 text-white' => $rojo,
                        'bg-primary-500 text-white' => $hecho && ! $rojo,
                        'bg-gray-100 text-gray-500 dark:bg-white/10' => ! $hecho,
                    ])>{{ $rojo ? '✕' : $i + 1 }}</span>
                    <span class="{{ $hecho ? 'font-medium text-gray-950 dark:text-white' : 'text-gray-500' }}">{{ $paso }}</span>
                    @if ($i < 2)<span class="mx-1 h-px w-8 bg-gray-200 dark:bg-white/10"></span>@endif
                </li>
            @endforeach
        </ol>
        <x-filament::badge :color="$estado->getColor()" :icon="$estado->getIcon()" size="lg">{{ $estado->getLabel() }}</x-filament::badge>
    </div>

    @if ($estado->value === 'expirada')
        <div class="rounded-xl bg-warning-50 p-3 text-sm text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400">
            Venció el {{ F::fecha($c->valida_hasta) }}. Duplícala para enviar una nueva propuesta.
        </div>
    @endif

    <div class="space-y-2">
        @foreach ($c->lineas as $l)
            @php $s = $l->subtotal(); @endphp
            <div class="flex items-start gap-3 rounded-xl p-3 ring-1 ring-gray-950/5 dark:ring-white/10">
                <x-tipo-chip :tipo="$l->tipo" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $l->nombre }}@if ($l->cantidad > 1) <span class="text-gray-500">× {{ $l->cantidad }}</span>@endif</p>
                    @if ($l->identificador)<p class="font-mono text-xs text-gray-700 dark:text-gray-300">{{ $l->identificador }}</p>@endif
                    @if ($l->detalle)<p class="text-xs text-gray-500 dark:text-gray-400">{{ $l->detalle }}</p>@endif
                    <div class="mt-2"><x-filament::badge color="gray">{{ $l->periodo->getLabel() }}</x-filament::badge></div>
                </div>
                @if ($verMontos)
                    <div class="text-right">
                        <p class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ F::pen($s['pen']) }}</p>
                        <p class="text-xs tabular-nums text-gray-500">{{ F::usd($s['usd']) }}{{ $c->aplica_igv ? ' · c/IGV' : '' }}</p>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($verMontos)
        <dl class="ml-auto w-full max-w-xs space-y-1.5 text-sm">
            @if ($c->aplica_igv)
                <div class="flex justify-between text-gray-600 dark:text-gray-300"><dt>Base imponible</dt><dd class="tabular-nums">{{ F::pen($t['subPen']) }}</dd></div>
                <div class="flex justify-between text-gray-600 dark:text-gray-300"><dt>IGV ({{ round($t['tasa'] * 100) }}%)</dt><dd class="tabular-nums">{{ F::pen($t['igvPen']) }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold text-gray-950 dark:border-white/10 dark:text-white"><dt>Total a pagar</dt><dd class="tabular-nums">{{ F::pen($t['totalPen']) }}</dd></div>
            <div class="flex justify-between text-xs text-gray-500"><dt>Equivalente · TC {{ F::num($c->tipo_cambio, 3) }}</dt><dd class="tabular-nums">{{ F::usd($t['totalUsd']) }}</dd></div>
        </dl>
    @endif

    @if (filled($c->condiciones_html))
        <section>
            <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Condiciones</h3>
            <div class="fi-prose max-w-none rounded-xl bg-gray-50 p-4 text-sm dark:bg-white/5">{!! \Illuminate\Support\Str::sanitizeHtml($c->condiciones_html) !!}</div>
        </section>
    @endif

    <div x-data="{ copiado: false }" class="flex items-center gap-2 rounded-xl bg-gray-50 p-3 text-sm dark:bg-white/5">
        <x-filament::icon icon="heroicon-o-link" class="h-4 w-4 shrink-0 text-gray-400" />
        <span class="min-w-0 flex-1 truncate font-mono text-xs text-gray-700 dark:text-gray-300">{{ $c->urlPublica() }}</span>
        <x-filament::button size="xs" color="gray"
            x-on:click="navigator.clipboard.writeText(@js($c->urlPublica())); copiado = true; setTimeout(() => copiado = false, 2000)">
            <span x-text="copiado ? 'Copiado' : 'Copiar'">Copiar</span>
        </x-filament::button>
    </div>
    <p class="-mt-4 text-xs text-gray-500">Cualquiera con el enlace puede ver el PDF.</p>
</div>
