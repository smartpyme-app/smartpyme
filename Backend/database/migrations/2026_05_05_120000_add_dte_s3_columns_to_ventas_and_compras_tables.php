<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDteS3ColumnsToVentasAndComprasTables extends Migration
{
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('dte_s3_key', 512)->nullable()->after('dte');
            $table->timestamp('dte_migrated_at')->nullable()->after('dte_s3_key');
            $table->string('dte_invalidacion_s3_key', 512)->nullable()->after('dte_invalidacion');
            $table->timestamp('dte_invalidacion_migrated_at')->nullable()->after('dte_invalidacion_s3_key');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->string('dte_s3_key', 512)->nullable()->after('dte');
            $table->timestamp('dte_migrated_at')->nullable()->after('dte_s3_key');
            $table->string('dte_invalidacion_s3_key', 512)->nullable()->after('dte_invalidacion');
            $table->timestamp('dte_invalidacion_migrated_at')->nullable()->after('dte_invalidacion_s3_key');
        });
    }

    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'dte_s3_key',
                'dte_migrated_at',
                'dte_invalidacion_s3_key',
                'dte_invalidacion_migrated_at',
            ]);
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn([
                'dte_s3_key',
                'dte_migrated_at',
                'dte_invalidacion_s3_key',
                'dte_invalidacion_migrated_at',
            ]);
        });
    }
}
