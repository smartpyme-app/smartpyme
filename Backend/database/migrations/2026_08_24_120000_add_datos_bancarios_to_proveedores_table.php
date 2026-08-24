<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('banco')->nullable();
            $table->string('tipo_cuenta')->nullable();
            $table->string('numero_cuenta', 50)->nullable();
            $table->string('titular_cuenta')->nullable();
            $table->string('forma_pago')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn([
                'banco',
                'tipo_cuenta',
                'numero_cuenta',
                'titular_cuenta',
                'forma_pago',
            ]);
        });
    }
};
