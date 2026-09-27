<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema de ServiciosTI según docs/SPEC.md y el modelo de datos del prototipo (docs/prototype/assets/data.js).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->default('tecnico')->after('password');
            $table->string('estado', 20)->default('activo')->after('rol');
            $table->timestamp('ultimo_acceso_at')->nullable()->after('estado');
        });

        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->char('ruc', 11);
            $table->string('email');
            $table->string('telefonos')->nullable();
            $table->string('direccion')->nullable();
            $table->string('web')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('yape_qr_path')->nullable();
            $table->string('yape_numero', 30)->nullable();
            $table->string('yape_titular')->nullable();
            $table->decimal('tipo_cambio', 8, 3)->default(3.750);
            $table->decimal('igv', 5, 4)->default(0.18);
            $table->unsignedSmallInteger('validez_cotizacion')->default(30);
            $table->timestamps();
        });

        Schema::create('empresa_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('banco', 60);
            $table->char('moneda', 3)->default('PEN');
            $table->string('numero', 40);
            $table->string('cci', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social');
            $table->string('tipo_doc', 5)->default('RUC');
            $table->string('documento', 20)->unique();
            $table->string('contacto')->nullable();
            $table->string('email')->unique();
            $table->string('email_secundario')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('direccion')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
        });

        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // vps | hosting
            $table->string('nombre');
            $table->unsignedSmallInteger('cpu')->nullable();
            $table->unsignedSmallInteger('ram')->nullable();
            $table->unsignedSmallInteger('sitios')->nullable();
            $table->unsignedInteger('disco')->nullable();
            $table->string('extra')->nullable();
            $table->decimal('precio_mensual', 12, 2)->nullable();
            $table->decimal('precio_semestral', 12, 2)->nullable();
            $table->decimal('precio_anual', 12, 2)->nullable();
            $table->char('moneda', 3)->default('USD');
            $table->boolean('destacado')->default(false);
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
        });

        Schema::create('servicios_varios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('precio_mensual', 12, 2)->nullable();
            $table->decimal('precio_semestral', 12, 2)->nullable();
            $table->decimal('precio_anual', 12, 2)->nullable();
            $table->decimal('precio_unico', 12, 2)->nullable();
            $table->char('moneda', 3)->default('PEN');
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
        });

        Schema::create('dominio_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('extension', 20)->unique();
            $table->decimal('precio_usd', 12, 2);
            $table->decimal('precio_pen', 12, 2);
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
        });

        Schema::create('dominio_registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained()->restrictOnDelete();
            $table->foreignId('dominio_tipo_id')->constrained()->restrictOnDelete();
            $table->string('nombre');
            $table->unsignedSmallInteger('cantidad')->default(1);
            $table->date('registrado_at')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
            $table->unique(['nombre', 'dominio_tipo_id']);
        });

        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('numero', 20)->unique();
            $table->foreignId('cliente_id')->constrained()->restrictOnDelete();
            $table->date('fecha');
            $table->date('valida_hasta');
            $table->string('estado', 20)->default('pendiente');
            $table->decimal('tipo_cambio', 8, 3);
            $table->boolean('aplica_igv')->default(true);
            $table->longText('condiciones_html')->nullable();
            $table->decimal('total_usd', 14, 2)->default(0);
            $table->decimal('total_pen', 14, 2)->default(0);
            $table->unsignedBigInteger('contrato_id')->nullable();
            $table->timestamps();
        });

        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('numero', 20)->unique();
            $table->foreignId('cliente_id')->constrained()->restrictOnDelete();
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones')->nullOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_renovacion')->nullable();
            $table->decimal('tipo_cambio', 8, 3);
            $table->boolean('aplica_igv')->default(true);
            $table->string('estado', 20)->default('borrador');
            $table->longText('condiciones_html')->nullable();
            $table->decimal('total_usd', 14, 2)->default(0);
            $table->decimal('total_pen', 14, 2)->default(0);
            $table->string('documento_evidencia_path')->nullable();
            $table->string('documento_evidencia_nombre')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        foreach (['contrato' => 'contratos', 'cotizacion' => 'cotizaciones'] as $padre => $tabla) {
            Schema::create("{$padre}_lineas", function (Blueprint $table) use ($padre, $tabla) {
                $table->id();
                $table->foreignId("{$padre}_id")->constrained($tabla)->cascadeOnDelete();
                $table->string('tipo', 20); // dominio | vps | hosting | vario
                $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
                $table->foreignId('servicio_vario_id')->nullable()->constrained('servicios_varios')->nullOnDelete();
                $table->foreignId('dominio_tipo_id')->nullable()->constrained('dominio_tipos')->nullOnDelete();
                $table->string('nombre');
                $table->string('identificador')->nullable();
                $table->string('detalle')->nullable();
                $table->unsignedInteger('cantidad')->default(1);
                $table->string('periodo', 20); // mensual | semestral | anual | unico
                $table->decimal('precio', 12, 2);
                $table->char('moneda', 3)->default('USD');
                $table->boolean('ocultar_usd')->default(false);
                $table->date('fecha_inicio')->nullable();
                // Vencimiento del periodo actual (inicio + meses); null en pago único. Se recalcula al guardar.
                $table->date('vence_el')->nullable()->index();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
            });
        }

        Schema::create('contrato_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('texto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_historial');
        Schema::dropIfExists('cotizacion_lineas');
        Schema::dropIfExists('contrato_lineas');
        Schema::dropIfExists('contratos');
        Schema::dropIfExists('cotizaciones');
        Schema::dropIfExists('dominio_registros');
        Schema::dropIfExists('dominio_tipos');
        Schema::dropIfExists('servicios_varios');
        Schema::dropIfExists('servicios');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('empresa_cuentas');
        Schema::dropIfExists('empresas');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['rol', 'estado', 'ultimo_acceso_at']));
    }
};
