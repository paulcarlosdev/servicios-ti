@php $tipo = $getRecord(); @endphp
<div class="flex items-center gap-3 px-3 py-2">
    <x-tipo-chip :tipo="\App\Enums\TipoLinea::Dominio" />
    <div class="min-w-0">
        <p class="font-mono text-base font-medium text-gray-950 dark:text-white">{{ $tipo->extension }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tipo->uso ? "en {$tipo->uso} contrato(s)" : 'Sin uso' }}</p>
    </div>
</div>
