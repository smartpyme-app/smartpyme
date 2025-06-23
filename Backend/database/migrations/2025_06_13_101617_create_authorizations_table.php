<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthorizationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('authorizations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique(); // Código único de 8 dígitos
            $table->foreignId('authorization_type_id')->constrained();
            $table->morphs('authorizeable'); // Relación polimórfica
            $table->unsignedInteger('id_empresa')->nullable();
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('authorized_by')->nullable()->constrained('users');
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->text('description');
            $table->json('data')->nullable();
            $table->text('notes')->nullable();
            $table->string('operation_type')->nullable();
            $table->json('operation_data')->nullable();
            $table->string('operation_hash')->nullable()->index();
            $table->timestamp('expires_at');
            $table->timestamp('authorized_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('authorizations');
    }
}
