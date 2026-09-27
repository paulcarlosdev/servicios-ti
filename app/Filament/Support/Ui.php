<?php

namespace App\Filament\Support;

use App\Enums\Estado;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

/** Piezas repetidas entre recursos: badge de estado, toggle activo/inactivo y pestañas por estado. */
class Ui
{
    /** Badge de estado (texto, color e ícono comunes a todo el panel). */
    public static function estadoColumna(string $name = 'estado', string $label = 'Estado'): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->badge()
            ->formatStateUsing(fn ($state) => self::estado($state)->getLabel())
            ->color(fn ($state) => self::estado($state)->getColor())
            ->icon(fn ($state) => self::estado($state)->getIcon());
    }

    public static function estado(mixed $state): Estado
    {
        return $state instanceof Estado ? $state : Estado::from((string) $state);
    }

    /** Toggle que guarda "activo" / "inactivo" en la columna estado. */
    public static function estadoToggle(string $label, ?string $ayuda = null): Toggle
    {
        return Toggle::make('estado')
            ->label($label)
            ->helperText($ayuda)
            ->default(true)
            ->formatStateUsing(fn ($state) => $state === null || $state === true || $state === 'activo')
            ->dehydrateStateUsing(fn ($state) => $state ? 'activo' : 'inactivo');
    }

    /** Pestañas Todos / Activos / Inactivos con contador. @return array<string, Tab> */
    public static function tabsActivos(string $modelo, string $todos = 'Todos'): array
    {
        return [
            'todos' => Tab::make($todos)->badge($modelo::count()),
            'activos' => Tab::make('Activos')
                ->badge($modelo::where('estado', 'activo')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', 'activo')),
            'inactivos' => Tab::make('Inactivos')
                ->badge($modelo::where('estado', 'inactivo')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', 'inactivo')),
        ];
    }
}
