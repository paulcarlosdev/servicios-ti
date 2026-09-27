<x-filament-widgets::widget>
    <x-filament::section heading="Permisos por rol" description="E: editar · V: ver · —: sin acceso">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400">
                        <th class="py-2 pr-4 font-medium">Módulo</th>
                        @foreach ($roles as $rol)
                            <th class="px-3 py-2 text-center font-medium">{{ $rol->getLabel() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($modulos as $clave => $modulo)
                        <tr>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $modulo }}</td>
                            @foreach ($roles as $rol)
                                @php $p = $permisos[$clave][$rol->value] ?? null; @endphp
                                <td @class([
                                    'px-3 py-2 text-center font-semibold',
                                    'text-success-600 dark:text-success-400' => $p === 'E',
                                    'text-info-600 dark:text-info-400' => $p === 'V',
                                    'text-gray-400' => $p === null,
                                ])>{{ $p ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Al desactivar un usuario se revocan sus sesiones abiertas.</p>
    </x-filament::section>
</x-filament-widgets::widget>
