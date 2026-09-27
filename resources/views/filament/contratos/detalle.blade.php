@php
    use App\Support\Formato as F;
    $c = $contrato;
    $estado = $c->estadoVisible();
    $prox = $c->proximaRenovacion();
    $t = $c->totales();
    $verMontos = auth()->user()->puedeVer('montos');
@endphp
<div class="space-y-6">
    <div class="flex justify-end">
        <x-filament::badge :color="$estado->getColor()" :icon="$estado->getIcon()" size="lg">{{ $estado->getLabel() }}</x-filament::badge>
    </div>

    <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (array_filter([
            ['Inicio', F::fecha($c->fecha_inicio), null],
            ['Próx. renovación', $prox ? F::fecha($prox) : '—', $prox ? F::rel($prox) : null],
            $verMontos ? ['Total', F::pen($t['totalPen']), F::usd($t['totalUsd']).($c->aplica_igv ? ' · con IGV' : '')] : null,
            ['Tipo de cambio', F::num($c->tipo_cambio, 3), 'USD → PEN'],
        ]) as [$label, $valor, $sub])
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                <dd class="mt-0.5 text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ $valor }}</dd>
                @if ($sub)<dd class="text-xs text-gray-500 dark:text-gray-400">{{ $sub }}</dd>@endif
            </div>
        @endforeach
    </dl>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section>
                <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Servicios ({{ $c->lineas->count() }})</h3>
                <div class="space-y-2">
                    @forelse ($c->lineas as $l)
                        @php
                            $alerta = $l->alerta();
                            $s = $l->subtotal();
                        @endphp
                        <div class="flex items-start gap-3 rounded-xl p-3 ring-1 ring-gray-950/5 dark:ring-white/10">
                            <x-tipo-chip :tipo="$l->tipo" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $l->nombre }}@if ($l->cantidad > 1) <span class="text-gray-500">× {{ $l->cantidad }}</span>@endif</p>
                                @if ($l->identificador)<p class="font-mono text-xs text-gray-700 dark:text-gray-300">{{ $l->identificador }}</p>@endif
                                @if ($l->detalle)<p class="text-xs text-gray-500 dark:text-gray-400">{{ $l->detalle }}</p>@endif
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <x-filament::badge color="gray">{{ $l->periodo->getLabel() }}</x-filament::badge>
                                    @if ($l->vence_el)
                                        <x-filament::badge icon="heroicon-m-arrow-path" :color="match ($alerta) { 'vencido' => 'danger', 'por_vencer' => 'warning', default => 'info' }">
                                            {{ $alerta === 'vencido' ? 'Venció' : 'Renueva' }} {{ F::fecha($l->vence_el) }} · {{ F::rel($l->vence_el) }}
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="gray">Pago único</x-filament::badge>
                                    @endif
                                    @if ($l->ocultar_usd)
                                        <x-filament::badge color="gray" icon="heroicon-m-eye-slash">USD oculto en PDF</x-filament::badge>
                                    @endif
                                </div>
                            </div>
                            @if ($verMontos)
                                <div class="text-right">
                                    <p class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ F::pen($s['pen']) }}</p>
                                    <p class="text-xs tabular-nums text-gray-500">{{ $l->ocultar_usd ? 'USD oculto' : F::usd($s['usd']) }}</p>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Todavía no hay servicios.</p>
                    @endforelse
                </div>
            </section>

            <section>
                <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Condiciones</h3>
                @if (filled($c->condiciones_html))
                    <div class="fi-prose max-w-none rounded-xl bg-gray-50 p-4 text-sm dark:bg-white/5">{!! \Illuminate\Support\Str::sanitizeHtml($c->condiciones_html) !!}</div>
                @else
                    <p class="text-sm text-gray-500">Sin condiciones.</p>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded-xl p-4 ring-1 ring-gray-950/5 dark:ring-white/10">
                <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">Cliente</h3>
                <div class="flex items-center gap-3">
                    <x-iniciales :nombre="$c->cliente->razon_social" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $c->cliente->razon_social }}</p>
                        <p class="text-xs text-gray-500">{{ $c->cliente->documento_completo }}</p>
                    </div>
                </div>
                <ul class="mt-3 space-y-1 text-sm text-gray-700 dark:text-gray-300">
                    @foreach (array_filter([$c->cliente->contacto, $c->cliente->email, $c->cliente->telefono]) as $dato)
                        <li class="truncate">{{ $dato }}</li>
                    @endforeach
                </ul>
            </section>

            <section class="rounded-xl p-4 ring-1 ring-gray-950/5 dark:ring-white/10">
                <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">Documentos</h3>
                <ul class="space-y-2 text-sm">
                    @if ($verMontos)
                        <li>
                            <a href="{{ \App\Filament\Support\AccionesDocumento::urlPdf($c) }}" target="_blank" class="flex items-center gap-2 hover:underline">
                                <x-filament::icon icon="heroicon-o-document-text" class="h-5 w-5 text-danger-500" />
                                <span class="flex-1"><span class="font-medium text-gray-950 dark:text-white">{{ $c->nombrePdf() }}</span><br><span class="text-xs text-gray-500">Generado por el sistema</span></span>
                            </a>
                        </li>
                    @endif
                    @if ($c->documento_evidencia_path)
                        <li>
                            <a href="{{ route('documentos.evidencia', $c) }}" class="flex items-center gap-2 hover:underline">
                                <x-filament::icon icon="heroicon-o-document-check" class="h-5 w-5 text-success-500" />
                                <span class="flex-1"><span class="font-medium text-gray-950 dark:text-white">{{ $c->documento_evidencia_nombre }}</span><br><span class="text-xs text-gray-500">Firmado por el cliente</span></span>
                                <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4 text-gray-400" />
                            </a>
                        </li>
                    @elseif ($c->documento_evidencia_nombre)
                        <li class="flex items-center gap-2 text-gray-500">
                            <x-filament::icon icon="heroicon-o-document-check" class="h-5 w-5" />
                            {{ $c->documento_evidencia_nombre }} <span class="text-xs">(registro de ejemplo, sin archivo)</span>
                        </li>
                    @else
                        <li class="text-xs text-gray-500">Sin documento firmado. Súbelo con «Subir documento firmado».</li>
                    @endif
                </ul>
            </section>

            <section class="rounded-xl p-4 ring-1 ring-gray-950/5 dark:ring-white/10">
                <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">Historial</h3>
                <ol class="relative space-y-3 border-l border-gray-200 pl-4 dark:border-white/10">
                    @foreach ($c->historial as $h)
                        <li>
                            <span class="absolute -left-1 mt-1.5 h-2 w-2 rounded-full bg-primary-500"></span>
                            <p class="text-sm text-gray-950 dark:text-white">{{ $h->texto }}</p>
                            <p class="text-xs text-gray-500">{{ F::fecha($h->created_at) }}@if ($h->user) · {{ $h->user->name }}@endif</p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>
</div>
