<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos_imagenes', function (Blueprint $table) {
            $table->string('src', 1000)->nullable()->after('img')->comment('URL original de la imagen en Shopify');
            $table->string('hash', 64)->nullable()->after('src')->comment('md5 del contenido descargado');
        });
    }

    public function down(): void
    {
        Schema::table('productos_imagenes', function (Blueprint $table) {
            $table->dropColumn(['src', 'hash']);
        });
    }
};
