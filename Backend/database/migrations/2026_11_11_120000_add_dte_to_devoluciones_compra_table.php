<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** FE 2.0 SV: Evento de retorno (tipo 18) sobre compras de sujeto excluido. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devoluciones_compra', function (Blueprint $table) {
            $table->string('tipo_dte', 2)->nullable()->after('tipo_documento');
            $table->string('codigo_generacion', 36)->nullable()->after('tipo_dte');
            $table->string('sello_mh')->nullable()->after('codigo_generacion');
            $table->longText('dte')->nullable()->after('sello_mh');
            $table->longText('dte_invalidacion')->nullable()->after('dte');
        });
    }

    public function down(): void
    {
        Schema::table('devoluciones_compra', function (Blueprint $table) {
            $table->dropColumn(['tipo_dte', 'codigo_generacion', 'sello_mh', 'dte', 'dte_invalidacion']);
        });
    }
};
