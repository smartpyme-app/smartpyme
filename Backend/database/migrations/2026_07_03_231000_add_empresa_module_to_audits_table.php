<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->unsignedBigInteger('id_empresa')->nullable()->after('user_id');
            $table->string('module', 50)->nullable()->after('id_empresa');
            $table->index(['id_empresa', 'module', 'created_at'], 'audits_empresa_module_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropIndex('audits_empresa_module_created_idx');
            $table->dropColumn(['id_empresa', 'module']);
        });
    }
};
