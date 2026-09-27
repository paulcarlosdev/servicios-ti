{{-- Pestañas de estado dentro de la barra de la tabla (ver App\Filament\Support\PestanasEnTabla) --}}
<x-filament::tabs class="!mx-0">
    @foreach ($pagina->getCachedTabs() as $clave => $tab)
        <x-filament::tabs.item
            :active="(string) $pagina->activeTab === (string) $clave"
            :badge="$tab->getBadge()"
            :badge-color="$tab->getBadgeColor()"
            :icon="$tab->getIcon()"
            wire:click="$set('activeTab', {{ \Illuminate\Support\Js::from((string) $clave) }})"
        >
            {{ $tab->getLabel() ?? \Illuminate\Support\Str::headline($clave) }}
        </x-filament::tabs.item>
    @endforeach
</x-filament::tabs>
