<?php

namespace App\Filament\Widgets;

use App\Enums\TipoLinea;
use App\Support\Renovaciones;
use Filament\Widgets\Widget;

class IngresoPorTipo extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.ingreso-por-tipo';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->puedeVer('montos');
    }

    protected function getViewData(): array
    {
        $mrr = Renovaciones::mrrPorTipo();
        $total = array_sum($mrr) ?: 1;
        $max = max($mrr) ?: 1;

        return [
            'filas' => collect(TipoLinea::cases())->map(fn (TipoLinea $t) => [
                'tipo' => $t,
                'monto' => $mrr[$t->value],
                'pct' => (int) round($mrr[$t->value] / $total * 100),
                'ancho' => max(2, $mrr[$t->value] / $max * 100),
            ]),
        ];
    }
}
