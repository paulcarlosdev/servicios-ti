<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Ficha única de la empresa propia (id = 1): cabecera de todos los PDF y configuración comercial. */
#[Guarded(['id'])]
class Empresa extends Model
{
    protected function casts(): array
    {
        return ['tipo_cambio' => 'decimal:3', 'igv' => 'decimal:4', 'validez_cotizacion' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => false); // registro único, no se elimina
    }

    /** Misma instancia durante la petición: al guardarla se actualiza para todos los lectores. */
    public static function actual(): self
    {
        return once(fn () => self::with('cuentas')->firstOrCreate(['id' => 1], [
            'razon_social' => 'Mi empresa S.A.C.', 'ruc' => '20000000000', 'email' => 'contacto@empresa.pe',
        ]));
    }

    public function cuentas(): HasMany
    {
        return $this->hasMany(EmpresaCuenta::class);
    }

    /** Ruta absoluta de una imagen en el disco public (para dompdf). */
    public function rutaImagen(?string $path): ?string
    {
        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->path($path) : null;
    }
}
