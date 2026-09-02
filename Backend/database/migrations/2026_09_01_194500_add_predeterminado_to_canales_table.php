<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canales', function (Blueprint $table) {
            $table->boolean('predeterminado')->default(false)->after('enable');
        });
    }

    public function down(): void
    {
        Schema::table('canales', function (Blueprint $table) {
            $table->dropColumn('predeterminado');
        });
    }
};
