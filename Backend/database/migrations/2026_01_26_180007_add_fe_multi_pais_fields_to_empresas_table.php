<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddFeMultiPaisFieldsToEmpresasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Campos críticos individuales (para consultas y validación)
            $table->string('fe_pais', 3)->nullable()->after('cod_pais')->comment('Código de país para FE (SV, CR, etc.)');
            $table->string('fe_usuario')->nullable()->after('fe_pais')->comment('Usuario genérico para FE');
            $table->string('fe_contrasena')->nullable()->after('fe_usuario')->comment('Contraseña genérica para FE');
            $table->string('fe_certificado_password')->nullable()->after('fe_contrasena')->comment('Contraseña del certificado digital');
            $table->string('fe_certificado_path')->nullable()->after('fe_certificado_password')->comment('Ruta al archivo del certificado digital');
            $table->text('fe_token')->nullable()->after('fe_certificado_path')->comment('Token de autenticación FE');
            $table->timestamp('fe_token_expires_at')->nullable()->after('fe_token')->comment('Fecha de expiración del token');
            
            // Campo JSON para configuraciones específicas por país (flexible)
            $table->json('fe_configuracion')->nullable()->after('fe_token_expires_at')->comment('Configuración específica por país (URLs personalizadas, parámetros, etc.)');
            
            // Índices para mejorar rendimiento de consultas
            $table->index('fe_pais');
            $table->index('fe_token_expires_at');
        });

        // Migrar datos existentes de El Salvador
        $this->migrateExistingData();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropIndex(['fe_pais']);
            $table->dropIndex(['fe_token_expires_at']);
            
            $table->dropColumn([
                'fe_pais',
                'fe_usuario',
                'fe_contrasena',
                'fe_certificado_password',
                'fe_certificado_path',
                'fe_token',
                'fe_token_expires_at',
                'fe_configuracion',
            ]);
        });
    }

    /**
     * Migra datos existentes de campos específicos de MH a campos genéricos
     * 
     * @return void
     */
    private function migrateExistingData(): void
    {
        // Migrar solo si los campos antiguos existen y los nuevos están vacíos
        // Esto hace que la migración sea idempotente
        DB::statement("
            UPDATE empresas 
            SET 
                fe_usuario = COALESCE(fe_usuario, mh_usuario),
                fe_contrasena = COALESCE(fe_contrasena, mh_contrasena),
                fe_certificado_password = COALESCE(fe_certificado_password, mh_pwd_certificado),
                fe_pais = COALESCE(fe_pais, CASE 
                    WHEN facturacion_electronica = 1 THEN 'SV' 
                    ELSE NULL 
                END)
            WHERE 
                (mh_usuario IS NOT NULL OR mh_contrasena IS NOT NULL OR mh_pwd_certificado IS NOT NULL)
                AND (fe_usuario IS NULL OR fe_contrasena IS NULL OR fe_certificado_password IS NULL)
        ");
    }
}
