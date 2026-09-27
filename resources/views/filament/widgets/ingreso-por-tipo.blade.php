@php
    use App\Support\Formato as F;
    $barras = ['sky' => 'bg-sky-500', 'violet' => 'bg-violet-500', 'emerald' => 'bg-emerald-500', 'amber' => 'bg-amber-500'];
@endphp
<x-filament-widgets::widget>
    <x-filament::section heading="Ingreso recurrente por tipo" description="Equivalente mensual en soles, sin IGV">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($filas as $f)
                <div title="{{ $f['tipo']->getLabel() }}: {{ F::pen($f['monto']) }} ({{ $f['pct'] }}%)">
                    <div class="mb-1.5 flex items-center gap-2 text-sm">
                        <x-tipo-chip :tipo="$f['tipo']" size="sm" />
                        <span class="flex-1 text-gray-700 dark:text-gray-200">{{ $f['tipo']->getLabel() }}</span>
                        <span class="tabular-nums font-medium text-gray-950 dark:text-white">{{ F::pen($f['monto']) }}</span>
                        <span class="w-9 text-right text-xs text-gray-500">{{ $f['pct'] }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-2 rounded-full {{ $barras[$f['tipo']->getColor()] }}" style="width: {{ $f['ancho'] }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
