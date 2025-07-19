<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('sello')->nullable()->after('logo');
            $table->string('firma')->nullable()->after('sello');
            $table->boolean('mostrar_sello_firma')->default(false)->after('firma');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['sello', 'firma', 'mostrar_sello_firma']);
        });
    }
};
