<ul class="space-y-2 text-sm">
    @foreach ($items as [$texto, $ok])
        <li class="flex items-center gap-2 {{ $ok ? 'text-gray-950 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">
            <x-filament::icon :icon="$ok ? 'heroicon-s-check-circle' : 'heroicon-o-minus-circle'" @class(['h-5 w-5', 'text-success-500' => $ok, 'text-gray-300 dark:text-gray-600' => ! $ok]) />
            {{ $texto }}
        </li>
    @endforeach
</ul>
