<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Filament\Resources\Clientes\Pages\ManageClientes;
use App\Filament\Resources\Contratos\Pages\ManageContratos;
use App\Filament\Resources\Cotizaciones\Pages\ManageCotizaciones;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cotizacion;
use App\Models\DominioRegistro;
use App\Models\DominioTipo;
use App\Models\Servicio;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.pe', 'password' => 'secreto123',
            'rol' => Rol::Admin, 'estado' => 'activo',
        ]);
        $this->actingAs($this->admin);
    }

    public function test_todas_las_pantallas_cargan(): void
    {
        $contrato = Contrato::first();
        $cotizacion = Cotizacion::first();
        $cliente = Cliente::first();

        foreach ([
            '/admin',
            '/admin/clientes',
            '/admin/clientes?tableAction=view&tableActionRecord='.$cliente->id,
            '/admin/contratos',
            '/admin/contratos?tab=vencido',
            '/admin/contratos?tableAction=view&tableActionRecord='.$contrato->id,
            '/admin/contratos?action=create',
            '/admin/cotizaciones',
            '/admin/cotizaciones?tableAction=view&tableActionRecord='.$cotizacion->id,
            '/admin/cotizaciones?action=create',
            '/admin/vps-hosting',
            '/admin/vps-hosting?tab=hosting',
            '/admin/servicios-varios',
            '/admin/dominios',
            '/admin/mi-empresa',
            '/admin/usuarios',
        ] as $url) {
            $this->assertSame(200, $this->get($url)->status(), "Falla: {$url}");
        }
    }

    public function test_pdfs_y_enlace_publico_por_uuid(): void
    {
        $contrato = Contrato::first();
        $cotizacion = Cotizacion::first();

        $this->get(route('documentos.contrato', $contrato))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('documentos.cotizacion', $cotizacion))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        auth()->logout();
        $this->get('/cotizacion/'.$cotizacion->uuid)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/cotizacion/'.$cotizacion->id)->assertNotFound();
        $this->get(route('documentos.contrato', $contrato))->assertRedirect('/admin/login');
    }

    public function test_estados_derivados_de_las_lineas(): void
    {
        $this->assertSame('vencido', Contrato::where('numero', 'CON-2026-0004')->first()->estadoVisible()->value);
        $this->assertSame('activo', Contrato::where('numero', 'CON-2026-0006')->first()->estadoVisible()->value);
        $this->assertSame('borrador', Contrato::where('numero', 'CON-2026-0008')->first()->estadoVisible()->value);
        $this->assertSame(1, Contrato::enEstado('vencido')->count());
        $this->assertSame(2, Contrato::enEstado('por_vencer')->count());
    }

    public function test_crear_contrato_con_lineas_y_activar(): void
    {
        $undo = Repeater::fake();
        $cliente = Cliente::where('razon_social', 'Nube Labs E.I.R.L.')->first();
        $pe = DominioTipo::where('extension', '.pe')->first();
        $plus = Servicio::where('nombre', 'Hosting Plus')->first();

        Livewire::test(ManageContratos::class)
            ->callAction('create', data: [
                'cliente_id' => $cliente->id,
                'fecha_inicio' => today()->toDateString(),
                'tipo_cambio' => 3.8,
                'aplica_igv' => true,
                'lineas' => [
                    ['tipo' => 'dominio', 'item' => $pe->id, 'dominio_tipo_id' => $pe->id, 'nombre' => 'Dominio .pe', 'moneda' => 'USD',
                        'identificador' => 'nubelabs', 'cantidad' => 1, 'periodo' => 'anual', 'precio' => 45, 'ocultar_usd' => false],
                    ['tipo' => 'hosting', 'item' => $plus->id, 'servicio_id' => $plus->id, 'nombre' => 'Hosting Plus', 'moneda' => 'USD',
                        'identificador' => 'nubelabs.pe', 'cantidad' => 1, 'periodo' => 'mensual', 'precio' => 18, 'ocultar_usd' => true],
                ],
            ], arguments: ['activar' => true])
            ->assertHasNoFormErrors();
        $undo();

        $contrato = Contrato::latest('id')->first();
        $this->assertSame('activo', $contrato->estado);
        $this->assertCount(2, $contrato->lineas);
        $this->assertSame('nubelabs.pe', $contrato->lineas[0]->identificador);
        $this->assertTrue($contrato->lineas[0]->vence_el->isSameDay(today()->addYear()));
        // (45 + 18) × 3.8 × 1.18
        $this->assertEqualsWithDelta(282.49, $contrato->total_pen, 0.01);
        $this->assertNotNull($contrato->pdf_path);
        $this->assertTrue(DominioRegistro::where('nombre', 'nubelabs')->where('dominio_tipo_id', $pe->id)->exists());
        $this->assertSame(['Contrato activado y PDF generado', 'Contrato creado'], $contrato->historial->pluck('texto')->all());
    }

    public function test_renovar_extiende_desde_el_vencimiento(): void
    {
        $contrato = Contrato::where('numero', 'CON-2026-0004')->first();
        $linea = $contrato->lineas->first();
        $venceAntes = $linea->vence_el->copy();

        Livewire::test(ManageContratos::class)
            ->callAction(TestAction::make('renovar')->table($contrato), data: [
                'lineas' => [$linea->id], 'tipo_cambio' => 3.9, 'ajuste' => 10,
            ])
            ->assertHasNoFormErrors();

        $linea->refresh();
        $this->assertTrue($linea->fecha_inicio->isSameDay($venceAntes));
        $this->assertTrue($linea->vence_el->isSameDay($venceAntes->copy()->addYear()));
        $this->assertEquals(49.5, $linea->precio);
        $this->assertEquals(3.9, $contrato->fresh()->tipo_cambio);
    }

    public function test_aceptar_y_convertir_cotizacion(): void
    {
        $cotizacion = Cotizacion::where('numero', 'COT-2026-0012')->first();

        Livewire::test(ManageCotizaciones::class)
            ->callAction(TestAction::make('marcarAceptada')->table($cotizacion))
            ->callAction(TestAction::make('convertir')->table($cotizacion), data: ['fecha_inicio' => today()->toDateString()]);

        $cotizacion->refresh();
        $this->assertSame('convertida', $cotizacion->estado);
        $contrato = $cotizacion->contrato;
        $this->assertSame('borrador', $contrato->estado);
        $this->assertSame($cotizacion->lineas->count(), $contrato->lineas->count());
        $this->assertSame('CON-'.now()->year.'-0009', $contrato->numero);
    }

    public function test_no_se_elimina_un_cliente_con_contratos(): void
    {
        $cliente = Cliente::where('razon_social', 'ACME S.A.C.')->first();

        Livewire::test(ManageClientes::class)->callAction(TestAction::make('eliminar')->table($cliente));

        $this->assertModelExists($cliente);
        $this->assertSame('inactivo', $cliente->fresh()->estado);
    }

    public function test_enviar_enlace_de_nueva_contrasena(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $gerente = User::where('email', 'maria@servicios-ti.pe')->first();

        Livewire::test(\App\Filament\Resources\Usuarios\Pages\ManageUsuarios::class)
            ->callAction(TestAction::make('enviarEnlace')->table($gerente));

        \Illuminate\Support\Facades\Notification::assertSentTo($gerente, \Illuminate\Auth\Notifications\ResetPassword::class,
            fn ($n) => str_contains($n->toMail($gerente)->actionUrl, '/admin/password-reset/reset'));
    }

    public function test_permisos_por_rol(): void
    {
        $tecnico = User::create(['name' => 'Téc', 'email' => 'tec@test.pe', 'password' => 'secreto123', 'rol' => Rol::Tecnico, 'estado' => 'activo']);
        $this->actingAs($tecnico);

        $this->get('/admin/contratos')->assertOk();
        $this->get('/admin/usuarios')->assertForbidden();
        $this->get('/admin/mi-empresa')->assertForbidden();
        $this->get(route('documentos.contrato', Contrato::first()))->assertForbidden();

        $inactivo = User::create(['name' => 'Off', 'email' => 'off@test.pe', 'password' => 'secreto123', 'rol' => Rol::Admin, 'estado' => 'inactivo']);
        $this->actingAs($inactivo);
        $this->get('/admin')->assertForbidden();
    }
}
