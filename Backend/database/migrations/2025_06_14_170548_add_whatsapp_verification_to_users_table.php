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
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_verification_code', 10)->nullable()->after('telefono');
            $table->timestamp('whatsapp_code_expires_at')->nullable()->after('whatsapp_verification_code');
            $table->boolean('whatsapp_verified')->default(false)->after('whatsapp_code_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_verification_code',
                'whatsapp_code_expires_at', 
                'whatsapp_verified'
            ]);
        });
    }
};