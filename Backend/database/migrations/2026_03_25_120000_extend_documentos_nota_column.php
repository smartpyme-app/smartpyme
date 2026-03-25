<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La nota del documento (pie de factura) admite hasta 1000 caracteres en validación;
 * TEXT evita truncado si la columna era VARCHAR corta.
 */
class ExtendDocumentosNotaColumn extends Migration
{
    public function up()
    {
        if (Schema::hasTable('documentos') && Schema::hasColumn('documentos', 'nota')) {
            Schema::table('documentos', function (Blueprint $table) {
                $table->text('nota')->nullable()->change();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('documentos') && Schema::hasColumn('documentos', 'nota')) {
            Schema::table('documentos', function (Blueprint $table) {
                $table->string('nota', 1000)->nullable()->change();
            });
        }
    }
}
