<?php

namespace App\Filament\Pages;

use App\Models\Empresa;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

/**
 * Mi empresa (HU-017): ficha única cuyos datos y logo encabezan los PDF,
 * más la configuración comercial por defecto (tipo de cambio, IGV y validez).
 *
 * @property-read Schema $form
 */
class MiEmpresa extends Page
{
    protected static ?string $title = 'Mi empresa';

    protected static ?string $slug = 'mi-empresa';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

    protected ?string $subheading = 'Estos datos encabezan los PDF de contratos y cotizaciones.';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->puedeVer('empresa') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(Empresa::actual()->attributesToArray());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model(Empresa::actual())
            ->operation('edit')
            ->statePath('data')
            ->disabled(! auth()->user()->puedeEditar('empresa'));
    }

    public function form(Schema $schema): Schema
    {
        $imagen = fn (string $campo, string $label) => FileUpload::make($campo)
            ->label($label)
            ->image()
            ->disk('public')
            ->directory('empresa')
            ->visibility('public')
            ->acceptedFileTypes(['image/png', 'image/jpeg'])
            ->maxSize(1024)
            ->imagePreviewHeight('96')
            ->validationMessages([
                'mimetypes' => 'Formato no válido: usa PNG o JPG.',
                'max' => 'Imagen muy pesada: máximo 1 MB.',
            ]);

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Group::make([
                    Section::make('Identidad')
                        ->description('Razón social, RUC y logo.')
                        ->columns(2)
                        ->schema([
                            $imagen('logo_path', 'Logo')
                                ->helperText('PNG o JPG, máximo 1 MB. Fondo transparente recomendado.')
                                ->live()
                                ->columnSpanFull(),
                            TextInput::make('razon_social')->label('Razón social')->required()->maxLength(255)->live(onBlur: true)
                                ->validationMessages(['required' => 'La razón social es obligatoria.']),
                            TextInput::make('nombre_comercial')->label('Nombre comercial')->maxLength(255),
                            TextInput::make('ruc')->label('RUC')->required()->regex('/^\d{11}$/')->maxLength(11)
                                ->inputMode('numeric')->helperText('11 dígitos.')->live(onBlur: true)
                                ->validationMessages(['regex' => 'El RUC debe tener 11 dígitos numéricos.']),
                        ]),

                    Section::make('Contacto')
                        ->description('Aparece en la cabecera y el pie del PDF.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('email')->label('Correo')->email()->required()->live(onBlur: true),
                            TextInput::make('telefonos')->label('Teléfonos')->helperText('Separa varios con “·”.')->live(onBlur: true),
                            TextInput::make('direccion')->label('Dirección fiscal')->columnSpanFull()->live(onBlur: true),
                            TextInput::make('web')->label('Sitio web')->prefix('https://'),
                        ]),

                    Section::make('Cuentas bancarias')
                        ->description('Se imprimen como formas de pago.')
                        ->schema([
                            Repeater::make('cuentas')
                                ->hiddenLabel()
                                ->relationship()
                                ->columns(4)
                                ->addActionLabel('Agregar cuenta')
                                ->defaultItems(0)
                                ->itemLabel(fn (array $state) => trim(($state['banco'] ?? '').' · '.($state['moneda'] ?? ''), ' ·'))
                                ->collapsible()
                                ->deleteAction(fn (Action $action) => $action->requiresConfirmation()
                                    ->modalHeading('Eliminar cuenta')
                                    ->modalDescription('La cuenta dejará de aparecer en los PDF.'))
                                ->schema([
                                    Select::make('banco')->label('Banco')->required()->default('BCP')
                                        ->options(array_combine($b = ['BCP', 'BBVA', 'Interbank', 'Scotiabank', 'BanBif', 'Banco de la Nación'], $b)),
                                    Select::make('moneda')->label('Moneda')->required()->default('PEN')
                                        ->options(['PEN' => 'Soles', 'USD' => 'Dólares']),
                                    TextInput::make('numero')->label('Número de cuenta')->required()->maxLength(40),
                                    TextInput::make('cci')->label('CCI')->required()->regex('/^\d{20}$/')->maxLength(20)
                                        ->inputMode('numeric')->helperText('20 dígitos.')
                                        ->validationMessages(['regex' => 'El CCI tiene 20 dígitos.']),
                                ]),
                        ]),

                    Section::make('Yape')
                        ->description('Número y QR para pagos rápidos.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('yape_numero')->label('Número')->tel(),
                            TextInput::make('yape_titular')->label('Titular'),
                            $imagen('yape_qr_path', 'Código QR')
                                ->helperText('Imagen del QR descargada desde la app de Yape.')
                                ->columnSpanFull(),
                        ]),

                    Section::make('Configuración comercial')
                        ->description('Valores por defecto de contratos y cotizaciones.')
                        ->columns(3)
                        ->schema([
                            TextInput::make('tipo_cambio')->label('Tipo de cambio vigente')->numeric()->step(0.001)
                                ->prefix('S/')->helperText('USD → PEN')->required()->gt(0)
                                ->validationMessages(['gt' => 'Mayor a 0.']),
                            TextInput::make('igv')->label('IGV')->numeric()->step(1)->suffix('%')->required()
                                ->minValue(0)->maxValue(100)
                                ->formatStateUsing(fn ($state) => $state === null ? 18 : round((float) $state * 100, 2))
                                ->dehydrateStateUsing(fn ($state) => (float) $state / 100),
                            TextInput::make('validez_cotizacion')->label('Validez de cotizaciones')->integer()
                                ->minValue(1)->suffix('días')->required(),
                        ]),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make('Vista previa del PDF')
                        ->description('Cabecera con los datos actuales del formulario.')
                        ->schema([
                            Html::make(fn (Get $get) => view('filament.empresa.cabecera', [
                                'razon' => $get('razon_social'),
                                'ruc' => $get('ruc'),
                                'direccion' => $get('direccion'),
                                'contacto' => collect([$get('telefonos'), $get('email')])->filter()->join(' · '),
                                'logo' => $this->urlImagen($get('logo_path')),
                            ])->render()),
                        ]),
                    Html::make('<p class="text-xs text-gray-500 dark:text-gray-400">Registro único: esta ficha no se puede eliminar.</p>'),
                ])->columnSpan(1),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Guardar cambios')->icon(Heroicon::OutlinedCheck)->submit('save')->keyBindings(['mod+s']),
                    ])->visible(auth()->user()->puedeEditar('empresa'))->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->puedeEditar('empresa'), 403);

        $empresa = Empresa::actual();
        $empresa->update($this->form->getState());
        $this->form->model($empresa)->saveRelationships();

        Notification::make()
            ->title('Datos de la empresa guardados')
            ->body('Los próximos PDF usarán esta información.')
            ->success()
            ->send();

        // Refresca el TC de la barra superior
        $this->redirect(static::getUrl(), navigate: true);
    }

    /** URL de vista previa: archivo recién subido (temporal) o ya guardado. */
    private function urlImagen(mixed $estado): ?string
    {
        $archivo = is_array($estado) ? reset($estado) : $estado;

        return match (true) {
            $archivo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile => $archivo->temporaryUrl(),
            is_string($archivo) && $archivo !== '' => Storage::disk('public')->url($archivo),
            default => null,
        };
    }
}
