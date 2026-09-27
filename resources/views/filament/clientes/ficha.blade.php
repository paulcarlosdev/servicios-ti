@php
    use App\Support\Formato as F;
    use App\Filament\Resources\Contratos\ContratoResource;
    use App\Filament\Resources\Cotizaciones\CotizacionResource;
    $lineas = $cliente->lineasVigentes()->with('contrato')->get();
    $contratos = $cliente->contratos()->with('lineas')->latest('id')->get();
    $cotizaciones = $cliente->cotizaciones()->latest('id')->get();
    $dominios = $cliente->dominios()->with('tipo')->get();
    $verMontos = auth()->user()->puedeVer('montos');
    $contacto = collect([
        ['heroicon-o-envelope', $cliente->email, null],
        ['heroicon-o-receipt-percent', $cliente->email_secundario, 'Facturación'],
        ['heroicon-o-phone', $cliente->telefono, null],
        ['heroicon-o-map-pin', $cliente->direccion, null],
    ])->filter(fn ($c) => filled($c[1]));
@endphp
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-5">
        <div class="flex items-center gap-3">
            <x-iniciales :nombre="$cliente->razon_social" size="lg" />
            <div>
                <p class="font-medium text-gray-950 dark:text-white">{{ $cliente->contacto }}</p>
                <p class="text-xs text-gray-500">Persona de contacto</p>
            </div>
            <x-filament::badge class="ml-auto" :color="\App\Filament\Support\Ui::estado($cliente->estado)->getColor()">
                {{ \App\Filament\Support\Ui::estado($cliente->estado)->getLabel() }}
            </x-filament::badge>
        </div>

        <ul class="space-y-2 text-sm">
            @foreach ($contacto as [$icono, $valor, $nota])
                <li class="flex items-start gap-2 text-gray-700 dark:text-gray-300">
                    <x-filament::icon :icon="$icono" class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                    <span class="break-all">{{ $valor }} @if ($nota)<span class="text-xs text-gray-500">({{ $nota }})</span>@endif</span>
                </li>
            @endforeach
        </ul>

        <dl class="grid grid-cols-2 gap-2">
            @foreach ([
                ['Contratos vigentes', $contratos->where('estado', 'activo')->count()],
                ['Servicios', $lineas->count()],
                ['Cobro mensual eq.', $verMontos ? F::pen($lineas->sum(fn ($l) => $l->mensualPen())) : '—'],
                ['Cotizaciones', $cotizaciones->count()],
            ] as [$label, $valor])
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-0.5 text-base font-semibold tabular-nums text-gray-950 dark:text-white">{{ $valor }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div class="space-y-6 lg:col-span-2">
        <section>
            <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Servicios contratados</h3>
            <div class="divide-y divide-gray-100 rounded-lg ring-1 ring-gray-950/5 dark:divide-white/5 dark:ring-white/10">
                @forelse ($lineas as $l)
                    @php $alerta = $l->alerta(); @endphp
                    <div class="flex items-center gap-3 p-3">
                        <x-tipo-chip :tipo="$l->tipo" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $l->etiqueta }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $l->nombre }} · {{ $l->periodo->getLabel() }} ·
                                <a href="{{ ContratoResource::urlVer($l->contrato_id) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $l->contrato->numero }}</a>
                            </p>
                        </div>
                        @if ($l->vence_el)
                            <x-filament::badge icon="heroicon-m-arrow-path" :color="match ($alerta) { 'vencido' => 'danger', 'por_vencer' => 'warning', default => 'gray' }">
                                {{ F::fecha($l->vence_el) }}
                            </x-filament::badge>
                        @else
                            <x-filament::badge color="gray">Pago único</x-filament::badge>
                        @endif
                    </div>
                @empty
                    <p class="p-4 text-sm text-gray-500">Este cliente todavía no tiene servicios activos.</p>
                @endforelse
            </div>
        </section>

        <section>
            <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Dominios registrados</h3>
            <div class="flex flex-wrap gap-2">
                @forelse ($dominios as $d)
                    <span class="rounded-md bg-sky-50 px-2 py-1 font-mono text-xs text-sky-700 ring-1 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-400">{{ $d->dominio }}</span>
                @empty
                    <p class="text-sm text-gray-500">Sin dominios.</p>
                @endforelse
            </div>
        </section>

        <section>
            <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Documentos</h3>
            <div class="space-y-1">
                @foreach ($contratos as $c)
                    @php $e = $c->estadoVisible(); @endphp
                    <a href="{{ ContratoResource::urlVer($c->id) }}" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-white/5">
                        <x-filament::icon icon="heroicon-o-document-text" class="h-4 w-4 text-gray-400" />
                        <span class="flex-1 font-medium text-gray-950 dark:text-white">{{ $c->numero }}</span>
                        <x-filament::badge :color="$e->getColor()" :icon="$e->getIcon()">{{ $e->getLabel() }}</x-filament::badge>
                    </a>
                @endforeach
                @foreach ($cotizaciones as $c)
                    @php $e = $c->estadoVisible(); @endphp
                    <a href="{{ CotizacionResource::urlVer($c->id) }}" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-white/5">
                        <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-4 w-4 text-gray-400" />
                        <span class="flex-1 font-medium text-gray-950 dark:text-white">{{ $c->numero }}</span>
                        <x-filament::badge :color="$e->getColor()" :icon="$e->getIcon()">{{ $e->getLabel() }}</x-filament::badge>
                    </a>
                @endforeach
                @if ($contratos->isEmpty() && $cotizaciones->isEmpty())
                    <p class="text-sm text-gray-500">Sin contratos ni cotizaciones.</p>
                @endif
            </div>
        </section>
    </div>
</div>
