<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionRevocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('permission_revocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->string('permission_name');
            $table->timestamps();

            // Índice único para evitar duplicados de revocación para el mismo usuario y permiso
            $table->unique(['user_id', 'permission_name']);

            // Índice para mejorar las búsquedas por user_id
            $table->index('user_id');
            
            // Índice para mejorar las búsquedas por permission_name
            $table->index('permission_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('permission_revocations');
    }
}