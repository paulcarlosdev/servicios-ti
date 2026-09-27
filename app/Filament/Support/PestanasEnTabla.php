<?php

namespace App\Filament\Support;

use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;

/**
 * Muestra las pestañas de la página dentro de la tarjeta de la tabla, a la izquierda del buscador
 * (como en docs/prototype), en lugar de centradas encima de la tabla.
 */
trait PestanasEnTabla
{
    public function bootPestanasEnTabla(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn () => view('filament.pestanas-tabla', ['pagina' => $this]),
            scopes: static::class,
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }
}
