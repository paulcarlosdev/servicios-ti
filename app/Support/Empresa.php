<?php

namespace App\Support;

use App\Models\Empresa as EmpresaModel;

/** Acceso rápido a la configuración comercial guardada en la ficha de la empresa. */
class Empresa
{
    public static function ficha(): EmpresaModel
    {
        return EmpresaModel::actual();
    }

    public static function tc(): float
    {
        return (float) self::ficha()->tipo_cambio;
    }

    public static function igv(): float
    {
        return (float) self::ficha()->igv;
    }

    public static function validez(): int
    {
        return (int) self::ficha()->validez_cotizacion;
    }
}
