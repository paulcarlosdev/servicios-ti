<?php

namespace App\Models\Contracts;

/** Algo que puede agregarse como línea de contrato o cotización (plan, servicio vario o extensión). */
interface ItemCatalogo
{
    /** @return array<string, float> periodo => precio, solo los periodos ofrecidos */
    public function precios(): array;

    public function monedaLinea(): string;

    public function nombreLinea(): string;

    public function detalleLinea(): string;
}
