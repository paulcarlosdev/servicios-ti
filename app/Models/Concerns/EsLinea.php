<?php

namespace App\Models\Concerns;

use App\Enums\Periodo;
use App\Enums\TipoLinea;
use App\Models\Contracts\ItemCatalogo;
use App\Models\DominioTipo;
use App\Models\Servicio;
use App\Models\ServicioVario;
use App\Support\Formato;
use App\Support\Montos;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comportamiento común de líneas de contrato y cotización.
 * vence_el = inicio del periodo actual + meses del periodo (sin desbordar fin de mes); null en pago único.
 */
trait EsLinea
{
    /** Fecha base del documento cuando la línea no tiene inicio propio. */
    abstract public function inicioDocumento(): ?CarbonInterface;

    abstract public function tipoCambio(): float;

    protected static function bootEsLinea(): void
    {
        static::saving(function (self $linea) {
            $linea->fecha_inicio ??= $linea->inicioDocumento();
            $meses = $linea->periodo?->meses() ?? 0;
            $linea->vence_el = $meses > 0 && $linea->fecha_inicio
                ? $linea->fecha_inicio->copy()->addMonthsNoOverflow($meses)
                : null;
        });
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoLinea::class,
            'periodo' => Periodo::class,
            'fecha_inicio' => 'date',
            'vence_el' => 'date',
            'ocultar_usd' => 'boolean',
            'precio' => 'float',
            'cantidad' => 'integer',
        ];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function servicioVario(): BelongsTo
    {
        return $this->belongsTo(ServicioVario::class);
    }

    public function dominioTipo(): BelongsTo
    {
        return $this->belongsTo(DominioTipo::class);
    }

    public function item(): ?ItemCatalogo
    {
        return match ($this->tipo) {
            TipoLinea::Dominio => $this->dominioTipo,
            TipoLinea::Vario => $this->servicioVario,
            default => $this->servicio,
        };
    }

    public function renueva(): bool
    {
        return ($this->periodo?->meses() ?? 0) > 0;
    }

    /** ok | vencido | por_vencer */
    public function alerta(): string
    {
        if (! $this->vence_el) {
            return 'ok';
        }
        $d = Formato::diff($this->vence_el);

        return match (true) {
            $d < 0 => 'vencido',
            $d <= $this->periodo->umbralAviso() => 'por_vencer',
            default => 'ok',
        };
    }

    /** @return array{usd: float, pen: float} */
    public function subtotal(): array
    {
        return Montos::subtotal($this->only(['precio', 'moneda', 'cantidad']), $this->tipoCambio());
    }

    /** @return array{usd: float, pen: float} */
    public function unitario(): array
    {
        return Montos::unitarios((float) $this->precio, $this->moneda, $this->tipoCambio());
    }

    public function mensualPen(): float
    {
        return Montos::mensual($this->only(['precio', 'moneda', 'cantidad', 'periodo']), $this->tipoCambio());
    }

    public function getEtiquetaAttribute(): string
    {
        return $this->identificador ?: $this->nombre;
    }

    /** Líneas vencidas o dentro de su ventana de aviso (7 días mensual, 30 días semestral/anual). */
    public function scopeConAlerta(Builder $query): void
    {
        $query->whereNotNull('vence_el')->where(fn (Builder $q) => $q
            ->where('vence_el', '<', today())
            ->orWhere(fn ($q) => $q->where('periodo', 'mensual')->where('vence_el', '<=', today()->addDays(7)))
            ->orWhere(fn ($q) => $q->where('periodo', '!=', 'mensual')->where('vence_el', '<=', today()->addDays(30))));
    }

    public function scopeVencidas(Builder $query): void
    {
        $query->whereNotNull('vence_el')->where('vence_el', '<', today());
    }
}
