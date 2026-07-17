<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (!Schema::hasColumn('empresas', 'woocommerce_api_key')) {
                $afterColumn = Schema::hasColumn('empresas', 'id_documento')
                    ? 'id_documento'
                    : null;

                $column = $table->string('woocommerce_api_key', 64)->nullable();

                if ($afterColumn) {
                    $column->after($afterColumn);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'woocommerce_api_key')) {
                $table->dropColumn('woocommerce_api_key');
            }
        });
    }
};
