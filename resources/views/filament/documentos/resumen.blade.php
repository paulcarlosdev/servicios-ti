@php use App\Support\Formato as F; @endphp
<div class="space-y-4 text-sm">
    <dl class="space-y-1.5">
        @if ($igv)
            <div class="flex justify-between text-gray-600 dark:text-gray-300">
                <dt>Base imponible</dt><dd class="tabular-nums">{{ F::pen($t['subPen']) }}</dd>
            </div>
            <div class="flex justify-between text-gray-600 dark:text-gray-300">
                <dt>IGV ({{ round($t['tasa'] * 100) }}%)</dt><dd class="tabular-nums">{{ F::pen($t['igvPen']) }}</dd>
            </div>
        @else
            <div class="flex justify-between text-gray-600 dark:text-gray-300"><dt>IGV</dt><dd>No aplica</dd></div>
        @endif
        <div class="flex items-baseline justify-between border-t border-gray-200 pt-2 dark:border-white/10">
            <dt class="font-semibold text-gray-950 dark:text-white">Total{{ $igv ? ' a pagar' : '' }}</dt>
            <dd class="text-xl font-bold tabular-nums text-gray-950 dark:text-white">{{ F::pen($t['totalPen']) }}</dd>
        </div>
        <p class="text-right text-xs text-gray-500 dark:text-gray-400">{{ $igv ? 'IGV incluido · ' : '' }}≈ {{ F::usd($t['totalUsd']) }} · TC {{ F::num($tc, 3) }}</p>
    </dl>

    @if ($renovaciones->isNotEmpty())
        <div>
            <p class="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Renovaciones</p>
            <ul class="space-y-1.5">
                @foreach ($renovaciones as $r)
                    @php $tipo = $r['tipo'] instanceof \App\Enums\TipoLinea ? $r['tipo'] : \App\Enums\TipoLinea::tryFrom($r['tipo'] ?? ''); @endphp
                    <li class="flex items-center gap-2">
                        @if ($tipo)<x-tipo-chip :tipo="$tipo" size="sm" />@endif
                        <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-300">{{ ($r['identificador'] ?? null) ?: ($r['nombre'] ?? '') }}</span>
                        <span class="text-xs tabular-nums text-gray-500">{{ F::fecha($r['vence']) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
