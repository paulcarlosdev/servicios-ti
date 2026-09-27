@php
    use App\Enums\Periodo;
    use App\Support\Formato as F;
    use App\Filament\Resources\Contratos\ContratoResource;
    $lineas = $servicio->lineasEnUso()->with('contrato.cliente')->get();
    $estado = \App\Filament\Support\Ui::estado($servicio->estado);
@endphp
<div class="space-y-5">
    <x-filament::badge :color="$estado->getColor()" :icon="$estado->getIcon()">{{ $estado->getLabel() }}</x-filament::badge>

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach (Periodo::cases() as $p)
            @php $precio = $servicio->{$p->columnaPrecio()}; @endphp
            <div @class(['rounded-lg p-3 ring-1 ring-gray-950/5 dark:ring-white/10', 'opacity-50' => ! $precio])>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $p->getLabel() }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-gray-950 dark:text-white">{{ $precio ? F::money($precio, $servicio->moneda) : '—' }}</p>
            </div>
        @endforeach
    </div>

    <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">En contratos ({{ $lineas->count() }})</h3>
        <div class="divide-y divide-gray-100 rounded-lg ring-1 ring-gray-950/5 dark:divide-white/5 dark:ring-white/10">
            @forelse ($lineas as $l)
                <div class="flex items-center gap-3 p-3 text-sm">
                    <a href="{{ ContratoResource::urlVer($l->contrato_id) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $l->contrato->numero }}</a>
                    <span class="flex-1 truncate text-gray-700 dark:text-gray-300">{{ $l->contrato->cliente->razon_social }}</span>
                    <x-filament::badge color="gray">{{ $l->periodo->getLabel() }}</x-filament::badge>
                </div>
            @empty
                <p class="p-4 text-sm text-gray-500">Aún no se ha contratado.</p>
            @endforelse
        </div>
    </section>
</div>
