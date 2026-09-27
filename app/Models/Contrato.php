<?php

namespace App\Models;

use App\Enums\Estado;
use App\Enums\TipoLinea;
use App\Models\Concerns\EsDocumento;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Contrato con líneas mixtas. Solo se guardan los estados borrador, activo y cancelado;
 * "vencido" y "por vencer" se derivan de las líneas (ver estadoVisible y scopeEnEstado).
 */
#[Guarded(['id'])]
class Contrato extends Model
{
    use EsDocumento;

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_renovacion' => 'date',
            'tipo_cambio' => 'float',
            'aplica_igv' => 'boolean',
            'total_usd' => 'float',
            'total_pen' => 'float',
        ];
    }

    public static function prefijo(): string
    {
        return 'CON';
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(ContratoLinea::class)->orderBy('orden')->orderBy('id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(ContratoHistorial::class)->latest('id');
    }

    public function vigente(): bool
    {
        return $this->estado === 'activo';
    }

    public function estadoVisible(): Estado
    {
        if ($this->estado !== 'activo') {
            return Estado::from($this->estado);
        }
        $alertas = $this->lineas->map->alerta();

        return match (true) {
            $alertas->contains('vencido') => Estado::Vencido,
            $alertas->contains('por_vencer') => Estado::PorVencer,
            default => Estado::Activo,
        };
    }

    /** Filtra por estado visible (activo, por_vencer, vencido, borrador, cancelado) en SQL. */
    public function scopeEnEstado(Builder $query, string $estado): void
    {
        match ($estado) {
            'borrador', 'cancelado' => $query->where('estado', $estado),
            'vencido' => $query->where('estado', 'activo')->whereHas('lineas', fn ($q) => $q->vencidas()),
            'por_vencer' => $query->where('estado', 'activo')
                ->whereDoesntHave('lineas', fn ($q) => $q->vencidas())
                ->whereHas('lineas', fn ($q) => $q->conAlerta()),
            'activo' => $query->where('estado', 'activo')->whereDoesntHave('lineas', fn ($q) => $q->conAlerta()),
            default => null,
        };
    }

    public function proximaRenovacion(): ?Carbon
    {
        return $this->vigente() ? $this->lineas->pluck('vence_el')->filter()->sort()->first() : null;
    }

    /** Ingreso mensual recurrente en soles, sin IGV. */
    public function ingresoMensual(): float
    {
        return $this->lineas->sum(fn (ContratoLinea $l) => $l->mensualPen());
    }

    public function registrar(string $texto): void
    {
        $this->historial()->create(['texto' => Str::limit($texto, 250), 'user_id' => auth()->id()]);
    }

    /** Crea el registro de dominio de cada línea de dominio que aún no exista (al activar o editar). */
    public function registrarDominios(): void
    {
        foreach ($this->lineas()->with('dominioTipo')->where('tipo', TipoLinea::Dominio)->get() as $linea) {
            if (! $linea->identificador || ! $linea->dominioTipo) {
                continue;
            }
            $nombre = Str::beforeLast($linea->identificador, $linea->dominioTipo->extension);
            DominioRegistro::firstOrCreate(
                ['nombre' => $nombre, 'dominio_tipo_id' => $linea->dominio_tipo_id],
                ['cliente_id' => $this->cliente_id, 'estado' => 'activo', 'registrado_at' => $linea->fecha_inicio ?? $this->fecha_inicio],
            );
        }
    }

    public function nombrePdf(): string
    {
        return "{$this->numero}.pdf";
    }
}
