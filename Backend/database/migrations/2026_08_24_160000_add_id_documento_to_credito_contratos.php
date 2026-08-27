<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credito_contratos', function (Blueprint $table) {
            $table->unsignedInteger('id_documento')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('credito_contratos', function (Blueprint $table) {
            $table->dropColumn('id_documento');
        });
    }
};
