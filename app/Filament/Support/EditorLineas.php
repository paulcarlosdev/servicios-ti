<?php

namespace App\Filament\Support;

use App\Enums\Periodo;
use App\Enums\TipoLinea;
use App\Models\Cliente;
use App\Models\Contracts\ItemCatalogo;
use App\Models\DominioRegistro;
use App\Models\DominioTipo;
use App\Models\Servicio;
use App\Models\ServicioVario;
use App\Support\Formato;
use App\Support\Montos;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Carbon;

/**
 * Editor de líneas mixtas compartido por contratos y cotizaciones (docs/prototype/assets/lineas.js).
 * Cada línea toma nombre, moneda, detalle y precio de lista del catálogo; el precio queda como copia en la línea.
 */
class EditorLineas
{
    public const SISTEMAS = ['Ubuntu 24.04 LTS', 'Debian 12', 'AlmaLinux 9', 'Rocky Linux 9', 'Windows Server 2022'];

    /** @param  string  $campoInicio  fecha base del documento: fecha_inicio (contrato) o fecha (cotización) */
    public static function repeater(string $campoInicio): Repeater
    {
        return Repeater::make('lineas')
            ->hiddenLabel()
            ->relationship()
            ->orderColumn('orden')
            ->defaultItems(0)
            ->addActionLabel('Agregar servicio')
            ->cloneable()
            ->collapsible()
            ->columns(12)
            ->itemLabel(fn (array $state) => self::etiqueta($state))
            // Filament inyecta por nombre: el parámetro debe llamarse $action
            ->deleteAction(fn (Action $action) => $action->tooltip('Quitar'))
            ->cloneAction(fn (Action $action) => $action->tooltip('Duplicar'))
            ->schema([
                Hidden::make('nombre'),
                Hidden::make('moneda'),
                Hidden::make('servicio_id'),
                Hidden::make('servicio_vario_id'),
                Hidden::make('dominio_tipo_id'),

                Select::make('tipo')
                    ->label('Tipo')
                    ->options(TipoLinea::class)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        foreach (['item', 'periodo', 'nombre', 'moneda', 'precio', 'detalle', 'identificador', 'servicio_id', 'servicio_vario_id', 'dominio_tipo_id'] as $campo) {
                            $set($campo, null);
                        }
                    })
                    ->columnSpan(['default' => 12, 'md' => 3]),

                Select::make('item')
                    ->label('Servicio del catálogo')
                    ->dehydrated(false)
                    ->required()
                    ->searchable()
                    ->live()
                    ->disabled(fn (Get $get) => blank($get('tipo')))
                    ->options(fn (Get $get) => self::opciones(self::tipo($get), $get('item')))
                    ->afterStateHydrated(fn (Select $component, Get $get) => $component->state(
                        $get('servicio_id') ?? $get('servicio_vario_id') ?? $get('dominio_tipo_id')
                    ))
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        $item = self::item(self::tipo($get), $state);
                        if (! $item) {
                            return;
                        }
                        $tipo = self::tipo($get);
                        $set('servicio_id', $item instanceof Servicio ? $item->id : null);
                        $set('servicio_vario_id', $item instanceof ServicioVario ? $item->id : null);
                        $set('dominio_tipo_id', $item instanceof DominioTipo ? $item->id : null);
                        $set('nombre', $item->nombreLinea());
                        $set('moneda', $item->monedaLinea());
                        $set('detalle', $tipo === TipoLinea::Vps
                            ? $item->detalleLinea().' · '.($get('sistema_operativo') ?: self::SISTEMAS[0])
                            : ($item->detalleLinea() ?: null));
                        // Anual si se ofrece (salvo VPS/hosting); si no, el primer periodo ofrecido
                        $precios = $item->precios();
                        $periodo = isset($precios['anual']) && ! in_array($tipo, [TipoLinea::Vps, TipoLinea::Hosting])
                            ? 'anual'
                            : array_key_first($precios);
                        $set('periodo', $periodo);
                        $set('precio', $precios[$periodo] ?? 0);
                    })
                    ->columnSpan(['default' => 12, 'md' => 5]),

                Select::make('periodo')
                    ->label('Periodo de pago')
                    ->required()
                    ->live()
                    ->selectablePlaceholder(false)
                    ->options(fn (Get $get) => self::opcionesPeriodo(self::item(self::tipo($get), $get('item'))))
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        $precio = self::item(self::tipo($get), $get('item'))?->precios()[$state instanceof Periodo ? $state->value : $state] ?? null;
                        if ($precio !== null) {
                            $set('precio', $precio);
                        }
                    })
                    ->columnSpan(['default' => 12, 'md' => 4]),

                TextInput::make('identificador')
                    ->label(fn (Get $get) => match (self::tipo($get)) {
                        TipoLinea::Dominio => 'Nombre de dominio',
                        TipoLinea::Vps => 'Hostname',
                        TipoLinea::Hosting => 'Dominio principal',
                        default => 'Referencia o alcance',
                    })
                    ->placeholder(fn (Get $get) => match (self::tipo($get)) {
                        TipoLinea::Dominio => 'miempresa',
                        TipoLinea::Vps => 'app.cliente.pe',
                        TipoLinea::Hosting => 'cliente.pe',
                        default => 'p. ej. 5 buzones, sede Surco',
                    })
                    ->helperText(fn (Get $get) => match (self::tipo($get)) {
                        TipoLinea::Dominio => 'Solo letras minúsculas, números y guiones. Se registrará a nombre del cliente.',
                        TipoLinea::Vps => 'Nombre con el que se identificará el servidor.',
                        TipoLinea::Hosting => 'Elige uno de los dominios del cliente o escribe otro.',
                        default => 'Opcional. Se imprime debajo del servicio.',
                    })
                    ->suffix(fn (Get $get) => self::tipo($get) === TipoLinea::Dominio ? DominioTipo::find($get('item'))?->extension : null)
                    ->datalist(fn (Get $get) => self::tipo($get) === TipoLinea::Hosting ? self::dominiosCliente($get) : null)
                    ->required(fn (Get $get) => self::tipo($get) !== TipoLinea::Vario)
                    ->maxLength(255)
                    // El dominio se guarda completo (nombre + extensión) y se edita sin la extensión
                    ->formatStateUsing(fn (?string $state, Get $get) => self::tipo($get) === TipoLinea::Dominio && $state
                        ? preg_replace('/'.preg_quote((string) DominioTipo::find($get('dominio_tipo_id'))?->extension, '/').'$/', '', $state)
                        : $state)
                    ->dehydrateStateUsing(fn (?string $state, Get $get) => match (self::tipo($get)) {
                        TipoLinea::Dominio => mb_strtolower(str_replace(' ', '', (string) $state)).DominioTipo::find($get('dominio_tipo_id'))?->extension,
                        TipoLinea::Hosting, TipoLinea::Vps => mb_strtolower(trim((string) $state)),
                        default => $state,
                    })
                    ->rule(fn (Get $get) => self::reglaIdentificador($get))
                    ->columnSpan(['default' => 12, 'md' => 6]),

                Select::make('sistema_operativo')
                    ->label('Sistema operativo')
                    ->options(array_combine(self::SISTEMAS, self::SISTEMAS))
                    ->default(self::SISTEMAS[0])
                    ->selectablePlaceholder(false)
                    ->dehydrated(false)
                    ->visible(fn (Get $get) => self::tipo($get) === TipoLinea::Vps)
                    ->live()
                    ->afterStateHydrated(function (Select $component, Get $get) {
                        $so = collect(self::SISTEMAS)->first(fn ($s) => str_ends_with((string) $get('detalle'), $s));
                        $component->state($so ?? self::SISTEMAS[0]);
                    })
                    ->afterStateUpdated(fn ($state, Get $get, Set $set) => $set('detalle',
                        self::item(TipoLinea::Vps, $get('item'))?->detalleLinea().' · '.$state))
                    ->columnSpan(['default' => 12, 'md' => 3]),

                TextInput::make('detalle')
                    ->label('Detalle')
                    ->maxLength(255)
                    ->visible(fn (Get $get) => in_array(self::tipo($get), [TipoLinea::Vps, TipoLinea::Hosting]))
                    ->columnSpan(['default' => 12, 'md' => 3]),

                TextInput::make('cantidad')
                    ->label('Cantidad')
                    ->integer()
                    ->minValue(1)
                    ->default(1)
                    ->required()
                    ->live(onBlur: true)
                    ->validationMessages(['min' => 'Mínimo 1.'])
                    ->columnSpan(['default' => 6, 'md' => 2]),

                TextInput::make('precio')
                    ->label(fn (Get $get) => $get('../../aplica_igv') ? 'Precio unitario (IGV incluido)' : 'Precio unitario')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0)
                    ->required()
                    ->live(onBlur: true)
                    ->prefix(fn (Get $get) => $get('moneda') === 'PEN' ? 'S/' : '$')
                    ->validationMessages(['min' => 'Ingresa un precio válido (no negativo).', 'required' => 'Ingresa un precio válido (no negativo).'])
                    ->hint(function (Get $get) {
                        $lista = self::precioLista($get);

                        return $lista !== null && abs($lista - (float) $get('precio')) > 0.001
                            ? 'Lista '.Formato::money($lista, $get('moneda'))
                            : null;
                    })
                    ->hintAction(Action::make('restablecer')
                        ->label('restablecer')
                        ->visible(fn (Get $get) => self::precioLista($get) !== null && abs(self::precioLista($get) - (float) $get('precio')) > 0.001)
                        ->action(fn (Get $get, Set $set) => $set('precio', self::precioLista($get))))
                    ->columnSpan(['default' => 6, 'md' => 3]),

                DatePicker::make('fecha_inicio')
                    ->label('Inicio del servicio')
                    ->default(fn (Get $get) => $get("../../{$campoInicio}"))
                    ->helperText('Por defecto, el inicio del documento.')
                    ->live()
                    ->columnSpan(['default' => 12, 'md' => 3]),

                Toggle::make('ocultar_usd')
                    ->label('Ocultar USD en el PDF')
                    ->helperText('El cliente verá solo el monto en soles.')
                    ->inline(false)
                    ->columnSpan(['default' => 12, 'md' => 4]),

                Text::make(fn (Get $get) => self::resumenLinea($get, $campoInicio))
                    ->color('gray')
                    ->columnSpanFull(),
            ]);
    }

    /** Aside "Resumen": subtotal, IGV, total y próximas renovaciones del documento. */
    public static function resumen(string $campoInicio): Html
    {
        return Html::make(function (Get $get) use ($campoInicio) {
            $lineas = collect($get('lineas') ?? [])->filter(fn ($l) => filled($l['precio'] ?? null));
            $tc = (float) $get('tipo_cambio') ?: 1;
            $t = Montos::totales($lineas->all(), $tc, (bool) $get('aplica_igv'));
            $renovaciones = $lineas
                ->map(function ($l) use ($get, $campoInicio) {
                    $periodo = self::periodoDe($l['periodo'] ?? null);
                    $inicio = $l['fecha_inicio'] ?? $get($campoInicio);
                    if (! $periodo || $periodo->meses() === 0 || ! $inicio) {
                        return null;
                    }

                    return $l + ['vence' => Carbon::parse($inicio)->addMonthsNoOverflow($periodo->meses())];
                })
                ->filter()
                ->sortBy('vence');

            return view('filament.documentos.resumen', ['t' => $t, 'tc' => $tc, 'igv' => (bool) $get('aplica_igv'), 'renovaciones' => $renovaciones])->render();
        });
    }

    public static function etiqueta(array $state): ?string
    {
        if (blank($state['nombre'] ?? null)) {
            return 'Nuevo servicio';
        }
        $cantidad = ($state['cantidad'] ?? 1) > 1 ? ' × '.$state['cantidad'] : '';
        $ident = filled($state['identificador'] ?? null) ? ' · '.$state['identificador'] : '';

        return $state['nombre'].$cantidad.$ident;
    }

    public static function tipo(Get $get): ?TipoLinea
    {
        $tipo = $get('tipo');

        return $tipo instanceof TipoLinea ? $tipo : TipoLinea::tryFrom((string) $tipo);
    }

    private static function periodoDe(mixed $periodo): ?Periodo
    {
        return $periodo instanceof Periodo ? $periodo : Periodo::tryFrom((string) $periodo);
    }

    public static function item(?TipoLinea $tipo, mixed $id): ?ItemCatalogo
    {
        if (! $tipo || blank($id)) {
            return null;
        }

        return once(fn () => match ($tipo) {
            TipoLinea::Dominio => DominioTipo::find($id),
            TipoLinea::Vario => ServicioVario::find($id),
            default => Servicio::find($id),
        });
    }

    /** Ítems activos del tipo (más el actual, aunque esté inactivo) con su precio "desde". */
    private static function opciones(?TipoLinea $tipo, mixed $actual): array
    {
        if (! $tipo) {
            return [];
        }
        $query = match ($tipo) {
            TipoLinea::Dominio => DominioTipo::query(),
            TipoLinea::Vario => ServicioVario::query(),
            default => Servicio::query()->where('tipo', $tipo->value),
        };

        return $query->where(fn ($q) => $q->where('estado', 'activo')->orWhere('id', $actual))
            ->orderBy('id')->get()
            ->mapWithKeys(function (ItemCatalogo $item) {
                $precios = $item->precios();
                $periodo = Periodo::from(array_key_first($precios) ?? 'mensual');
                $desde = $precios ? ' · desde '.Formato::money(reset($precios), $item->monedaLinea()).$periodo->sufijo() : '';
                $popular = ($item->destacado ?? false) ? ' ★' : '';

                return [$item->id => ($item instanceof DominioTipo ? $item->extension : $item->nombreLinea()).$popular.$desde];
            })
            ->all();
    }

    private static function opcionesPeriodo(?ItemCatalogo $item): array
    {
        if (! $item) {
            return collect(Periodo::cases())->mapWithKeys(fn (Periodo $p) => [$p->value => $p->getLabel()])->all();
        }
        $precios = $item->precios();

        return collect($precios)->mapWithKeys(function (float $precio, string $clave) use ($precios, $item) {
            $p = Periodo::from($clave);
            $ahorro = Montos::ahorro($precios['mensual'] ?? null, $precio, $p);

            return [$clave => $p->getLabel().' — '.Formato::money($precio, $item->monedaLinea()).($ahorro ? " · −{$ahorro}%" : '')];
        })->all();
    }

    private static function precioLista(Get $get): ?float
    {
        $periodo = self::periodoDe($get('periodo'));

        return $periodo ? (self::item(self::tipo($get), $get('item'))?->precios()[$periodo->value] ?? null) : null;
    }

    /** Dominios del cliente (registrados + líneas de dominio del documento) para sugerir en hosting. */
    private static function dominiosCliente(Get $get): array
    {
        $enDocumento = collect($get('../../lineas') ?? [])
            ->filter(fn ($l) => ($l['tipo'] ?? null) === 'dominio' || ($l['tipo'] ?? null) === TipoLinea::Dominio)
            ->map(fn ($l) => $l['identificador'] ?? null);
        $registrados = DominioRegistro::with('tipo')->where('cliente_id', $get('../../cliente_id'))->get()->map->dominio;

        return $enDocumento->merge($registrados)->filter()->unique()->values()->all();
    }

    private static function reglaIdentificador(Get $get): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($get) {
            $valor = mb_strtolower(trim((string) $value));
            match (self::tipo($get)) {
                TipoLinea::Dominio => self::validarDominio($valor, $get, $fail),
                TipoLinea::Vps => preg_match('/^[a-z0-9.-]{3,}$/i', $valor) ?: $fail('Escribe un hostname válido, p. ej. app.cliente.pe.'),
                TipoLinea::Hosting => preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/i', $valor) ?: $fail('Escribe un dominio válido, p. ej. cliente.pe.'),
                default => null,
            };
        };
    }

    private static function validarDominio(string $nombre, Get $get, \Closure $fail): void
    {
        $nombre = str_replace(' ', '', $nombre);
        if (! preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $nombre)) {
            $fail('Usa solo letras, números y guiones (sin espacios ni guion al inicio o final).');

            return;
        }
        $tipo = DominioTipo::find($get('dominio_tipo_id'));
        $registro = DominioRegistro::with('cliente')->where('nombre', $nombre)->where('dominio_tipo_id', $tipo?->id)->first();
        if ($registro && $registro->cliente_id != $get('../../cliente_id')) {
            $fail("{$nombre}{$tipo?->extension} ya está registrado para {$registro->cliente->razon_social}.");

            return;
        }
        $completo = $nombre.$tipo?->extension;
        $repetidos = collect($get('../../lineas') ?? [])
            ->filter(fn ($l) => self::tipoDe($l) === TipoLinea::Dominio && ($l['dominio_tipo_id'] ?? null) == $tipo?->id)
            ->filter(fn ($l) => in_array(mb_strtolower((string) ($l['identificador'] ?? '')), [$nombre, $completo], true))
            ->count();
        if ($repetidos > 1) {
            $fail('Este dominio ya está en la lista.');
        }
    }

    private static function tipoDe(array $linea): ?TipoLinea
    {
        $tipo = $linea['tipo'] ?? null;

        return $tipo instanceof TipoLinea ? $tipo : TipoLinea::tryFrom((string) $tipo);
    }

    private static function resumenLinea(Get $get, string $campoInicio): string
    {
        if (blank($get('precio')) || blank($get('moneda'))) {
            return 'Elige un servicio para ver el subtotal.';
        }
        $tc = (float) $get('../../tipo_cambio') ?: 1;
        $igv = (bool) $get('../../aplica_igv');
        $s = Montos::subtotal(['precio' => $get('precio'), 'moneda' => $get('moneda'), 'cantidad' => $get('cantidad')], $tc);
        $periodo = self::periodoDe($get('periodo'));
        $inicio = $get('fecha_inicio') ?: $get("../../{$campoInicio}");
        $renovacion = $periodo && $periodo->meses() > 0 && $inicio
            ? 'Primera renovación el '.Formato::fecha(Carbon::parse($inicio)->addMonthsNoOverflow($periodo->meses()))
            : 'Pago único, no se renueva';

        return 'Total '.Formato::pen($s['pen']).' ('.Formato::usd($s['usd']).')'.($igv ? ' IGV incluido' : ' sin IGV').' · '.$renovacion;
    }
}
