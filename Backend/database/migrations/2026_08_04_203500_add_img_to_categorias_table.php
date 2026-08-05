<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categorias')) {
            return;
        }
        if (Schema::hasColumn('categorias', 'img')) {
            return;
        }
        Schema::table('categorias', function (Blueprint $table) {
            $table->string('img')->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('categorias') || ! Schema::hasColumn('categorias', 'img')) {
            return;
        }
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropColumn('img');
        });
    }
};
