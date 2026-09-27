<?php

namespace App\Filament\Support;

use App\Mail\DocumentoEnviado;
use App\Models\Contrato;
use App\Models\Cotizacion;
use App\Services\DocumentoPdf;
use App\Support\Empresa;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Html;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

/** Acciones comunes a contratos y cotizaciones: vista previa del PDF y envío por correo. */
class AccionesDocumento
{
    public static function urlPdf(Contrato|Cotizacion $doc, bool $descargar = false): string
    {
        $params = $descargar ? ['descargar' => 1] : [];

        return $doc instanceof Contrato
            ? route('documentos.contrato', [$doc, ...$params])
            : route('documentos.cotizacion', [$doc, ...$params]);
    }

    /** Vista previa del PDF en modal ancho, con descarga y envío. */
    public static function verPdf(string $nombre = 'verPdf'): Action
    {
        return Action::make($nombre)
            ->label('Ver PDF')
            ->icon(Heroicon::OutlinedDocumentText)
            ->visible(fn () => auth()->user()->puedeVer('montos'))
            ->modalHeading(fn (Contrato|Cotizacion $record) => "{$record->numero} · PDF")
            ->modalDescription(fn (Contrato|Cotizacion $record) => "{$record->cliente->razon_social} · {$record->nombrePdf()}")
            ->modalWidth(Width::SixExtraLarge)
            ->schema(fn (Contrato|Cotizacion $record) => [
                Html::make('<iframe src="'.e(self::urlPdf($record)).'" title="'.e($record->nombrePdf()).'" class="h-[75vh] w-full rounded-lg ring-1 ring-gray-950/10"></iframe>'),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(fn (Contrato|Cotizacion $record) => [
                Action::make('descargarPdf')
                    ->label('Descargar PDF')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(self::urlPdf($record, descargar: true)),
            ]);
    }

    public static function enviarCorreo(): Action
    {
        return Action::make('enviarCorreo')
            ->label('Enviar por correo')
            ->icon(Heroicon::OutlinedEnvelope)
            ->visible(fn () => auth()->user()->puedeEditar('documentos'))
            ->modalHeading(fn (Contrato|Cotizacion $record) => $record instanceof Contrato ? 'Enviar contrato' : 'Enviar cotización')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Enviar')
            ->fillForm(fn (Contrato|Cotizacion $record) => self::correoPorDefecto($record))
            ->schema(fn (Contrato|Cotizacion $record) => [
                TextInput::make('para')->label('Para')->email()->required()
                    ->validationMessages(['email' => 'Ingresa un correo válido.']),
                TextInput::make('cc')->label('Copia (CC)')->email()->placeholder('Opcional'),
                TextInput::make('asunto')->label('Asunto')->required()
                    ->validationMessages(['required' => 'El asunto es obligatorio.']),
                Textarea::make('mensaje')->label('Mensaje')->rows(8),
                Html::make('<p class="text-sm text-gray-500">📎 '.e($record->nombrePdf()).' · Se adjunta automáticamente</p>'),
            ])
            ->action(function (array $data, Contrato|Cotizacion $record) {
                $mail = Mail::to($data['para']);
                if (filled($data['cc'])) {
                    $mail->cc($data['cc']);
                }
                $mail->send(new DocumentoEnviado($data['asunto'], (string) $data['mensaje'], app(DocumentoPdf::class)->contenido($record), $record->nombrePdf()));

                if ($record instanceof Contrato) {
                    $record->registrar("PDF enviado a {$data['para']}");
                }
                Notification::make()->title('Correo enviado')->body("Se envió {$record->nombrePdf()} a {$data['para']}.")->success()->send();
            });
    }

    private static function correoPorDefecto(Contrato|Cotizacion $doc): array
    {
        $empresa = Empresa::ficha();
        $cliente = $doc->cliente;
        $esContrato = $doc instanceof Contrato;
        $total = Formato::pen($doc->total_pen).($doc->aplica_igv ? ' (IGV incluido)' : '');
        $intro = $esContrato
            ? "Le enviamos el contrato {$doc->numero}, por un total de {$total}."
            : 'Le enviamos la cotización '.$doc->numero.', válida hasta el '.Formato::fecha($doc->valida_hasta).", por un total de {$total}.";
        $lineas = $doc->lineas->map(fn ($l) => '· '.$l->nombre.($l->identificador ? " ({$l->identificador})" : '').' — '.mb_strtolower($l->periodo->getLabel()))->join("\n");
        $comercial = $empresa->nombre_comercial ?: $empresa->razon_social;

        return [
            'para' => $cliente->email,
            'cc' => $cliente->email_secundario,
            'asunto' => ($esContrato ? 'Contrato' : 'Cotización')." {$doc->numero} · {$comercial}",
            'mensaje' => "Estimado(a) {$cliente->contacto}:\n\n{$intro}\n\n{$lineas}\n\nQuedamos atentos a sus consultas.\n\n{$empresa->razon_social}\n{$empresa->telefonos}",
        ];
    }
}
