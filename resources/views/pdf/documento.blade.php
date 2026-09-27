@php
    use App\Support\Formato as F;
    $cliente = $doc->cliente;
    $logo = $empresa->rutaImagen($empresa->logo_path);
    $qr = $empresa->rutaImagen($empresa->yape_qr_path);
    $algunUsd = $doc->algunUsd();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $doc->numero }}</title>
<style>
    @page { margin: 28px 34px 50px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1f2937; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: #6b7280; }
    .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 9.5px; }
    .right { text-align: right; }
    .head { border-bottom: 3px solid #111827; padding-bottom: 10px; margin-bottom: 14px; }
    .logo { width: 56px; height: 56px; }
    .logo-ph { width: 56px; height: 40px; padding-top: 16px; background: #111827; color: #fff; font-weight: bold; font-size: 20px; text-align: center; line-height: 1; }
    .kicker { font-size: 9px; letter-spacing: 1px; text-transform: uppercase; color: #6b7280; }
    .numero { font-size: 18px; font-weight: bold; }
    .box { background: #f3f4f6; padding: 10px 12px; margin-bottom: 14px; }
    .lineas th { text-align: left; font-size: 9px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 6px 4px; }
    .lineas td { border-bottom: 1px solid #e5e7eb; padding: 7px 4px; vertical-align: top; }
    .totales { width: 260px; margin-left: auto; margin-top: 10px; }
    .totales td { padding: 3px 4px; }
    .total td { border-top: 2px solid #111827; font-weight: bold; font-size: 12px; padding-top: 6px; }
    .condiciones { margin-top: 18px; }
    .condiciones p { margin: 0 0 6px; }
    .pagos { border: 1px solid #d1d5db; padding: 10px 12px; margin-top: 16px; }
    .yape-qr { border: 1px solid #742284; text-align: center; padding: 5px 4px 4px; }
    .yape-titulo { background: #742284; color: #fff; font-weight: bold; font-size: 9.5px; padding: 3px 0; margin: -5px -4px 5px; }
    .yape-nota { font-size: 7.5px; color: #742284; margin-top: 3px; line-height: 1.2; }
    .firmas { margin-top: 60px; page-break-inside: avoid; }
    .firmas td { width: 50%; padding: 0 20px; text-align: center; }
    .firma { border-top: 1px solid #111827; padding-top: 6px; }
    footer { position: fixed; bottom: -34px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #9ca3af; }
</style>
</head>
<body>
<footer>{{ $empresa->razon_social }} · {{ $doc->numero }} · {{ $empresa->web }}</footer>

<table class="head">
    <tr>
        <td style="width: 64px; vertical-align: top;">
            @if ($logo)
                <img src="{{ $logo }}" class="logo">
            @else
                <div class="logo-ph">TI</div>
            @endif
        </td>
        <td style="vertical-align: top;">
            <strong style="font-size: 13px;">{{ $empresa->razon_social }}</strong><br>
            RUC {{ $empresa->ruc }}<br>
            <span class="muted">{{ $empresa->direccion }}</span><br>
            <span class="muted">{{ collect([$empresa->telefonos, $empresa->email])->filter()->join(' · ') }}</span>
        </td>
        <td class="right" style="vertical-align: top;">
            <div class="kicker">{{ $esContrato ? 'Contrato de servicios' : 'Cotización' }}</div>
            <div class="numero">{{ $doc->numero }}</div>
            @if ($esContrato)
                Inicio: {{ F::fecha($doc->fecha_inicio) }}<br>
            @else
                Emitida: {{ F::fecha($doc->fecha) }}<br>
                Válida hasta: {{ F::fecha($doc->valida_hasta) }}<br>
            @endif
            <span class="muted">TC USD→PEN: {{ F::num($doc->tipo_cambio, 3) }}</span>
        </td>
    </tr>
</table>

<table class="box">
    <tr>
        <td style="vertical-align: top;">
            <div class="kicker">Cliente</div>
            <strong>{{ $cliente->razon_social }}</strong><br>
            {{ $cliente->documento_completo }}
        </td>
        <td class="right" style="vertical-align: top;">
            {{ $cliente->contacto }}<br>
            {{ $cliente->email }}<br>
            <span class="muted">{{ $cliente->direccion }}</span>
        </td>
    </tr>
</table>

<table class="lineas">
    <thead>
        <tr>
            <th style="width: 18px;">#</th>
            <th>Servicio</th>
            <th>Periodo</th>
            <th class="right">Cant.</th>
            <th class="right">P. unitario</th>
            <th class="right">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($doc->lineas as $i => $l)
            @php
                $u = $l->unitario();
                $s = $l->subtotal();
                $vence = $l->vence_el;
            @endphp
            <tr>
                <td class="muted">{{ $i + 1 }}</td>
                <td>
                    <strong>{{ $l->nombre }}</strong>
                    @if ($l->identificador)<br><span class="mono">{{ $l->identificador }}</span>@endif
                    @if ($l->detalle)<br><span class="muted">{{ $l->detalle }}</span>@endif
                </td>
                <td>
                    {{ $l->periodo->getLabel() }}<br>
                    <span class="muted">{{ $vence ? 'Renueva '.F::fecha($vence) : 'Sin renovación' }}</span>
                </td>
                <td class="right">{{ $l->cantidad }}</td>
                <td class="right">
                    {{ F::pen($u['pen']) }}
                    @unless ($l->ocultar_usd)<br><span class="muted">{{ F::usd($u['usd']) }}</span>@endunless
                </td>
                <td class="right">
                    {{ F::pen($s['pen']) }}
                    @unless ($l->ocultar_usd)<br><span class="muted">{{ F::usd($s['usd']) }}</span>@endunless
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totales">
    <tr><td>Subtotal</td><td class="right">{{ F::pen($totales['subPen']) }}</td></tr>
    @if ($doc->aplica_igv)
        <tr><td>IGV ({{ round($totales['tasa'] * 100) }}%)</td><td class="right">{{ F::pen($totales['igvPen']) }}</td></tr>
    @endif
    <tr class="total"><td>Total</td><td class="right">{{ F::pen($totales['totalPen']) }}</td></tr>
    @if ($algunUsd)
        <tr><td class="muted">Equivalente</td><td class="right muted">{{ F::usd($totales['totalUsd']) }}</td></tr>
    @endif
</table>

@if (filled($doc->condiciones_html))
    <div class="condiciones">
        <div class="kicker" style="margin-bottom: 6px;">Condiciones</div>
        {!! \Illuminate\Support\Str::sanitizeHtml($doc->condiciones_html) !!}
    </div>
@endif

@if ($empresa->cuentas->isNotEmpty() || $empresa->yape_numero)
    <table class="pagos">
        <tr>
            <td style="vertical-align: top;">
                <div class="kicker" style="margin-bottom: 4px;">Formas de pago</div>
                @foreach ($empresa->cuentas as $cuenta)
                    <strong>{{ $cuenta->banco }} {{ $cuenta->moneda }}</strong> · Cta. {{ $cuenta->numero }} · CCI {{ $cuenta->cci }}<br>
                @endforeach
                @if ($empresa->yape_numero)
                    <strong>Yape</strong> · {{ $empresa->yape_numero }} ({{ $empresa->yape_titular }})@if ($qr) · <span class="muted">o escanea el QR de Yape</span>@endif
                @endif
            </td>
            @if ($qr)
                {{-- Rótulo junto al QR para que se entienda que es para pagar con Yape --}}
                <td style="width: 110px; vertical-align: top;">
                    <div class="yape-qr">
                        <div class="yape-titulo">Paga con Yape</div>
                        <img src="{{ $qr }}" style="width: 84px; height: 84px;">
                        <div class="yape-nota">Escanéalo desde<br>tu app Yape</div>
                    </div>
                </td>
            @endif
        </tr>
    </table>
@endif

@if ($esContrato)
    <table class="firmas">
        <tr>
            <td><div class="firma"><strong>{{ $empresa->razon_social }}</strong><br>RUC {{ $empresa->ruc }}</div></td>
            <td><div class="firma"><strong>{{ $cliente->razon_social }}</strong><br>{{ $cliente->documento_completo }}</div></td>
        </tr>
    </table>
@else
    <p class="muted" style="margin-top: 18px;">Cotización válida hasta el {{ F::fecha($doc->valida_hasta) }}. Precios sujetos a disponibilidad.</p>
@endif
</body>
</html>
