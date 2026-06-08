<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abonos_gastos', function (Blueprint $table) {
            $table->string('correlativo')->nullable()->after('fecha');
            $table->unsignedInteger('id_documento')->nullable()->after('correlativo');
        });
    }

    public function down(): void
    {
        Schema::table('abonos_gastos', function (Blueprint $table) {
            $table->dropColumn(['correlativo', 'id_documento']);
        });
    }
};
