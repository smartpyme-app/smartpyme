<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDteDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dte_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('user_email_account_id');
            $table->string('dte_uuid')->comment('UUID from MH JSON - id field');
            $table->string('dte_type', 5)->comment('01=FC, 03=CCF, 04=NR, 05=NC, 06=ND, 11=FEX');
            $table->string('dte_number')->nullable();
            $table->date('emission_date')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('issuer_nit')->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('receiver_nit')->nullable();
            $table->string('json_path')->nullable()->comment('Path in storage');
            $table->string('pdf_path')->nullable();
            $table->enum('validation_status', ['pending', 'valid', 'invalid'])->default('pending');
            $table->json('validation_errors')->nullable();
            $table->enum('processing_status', ['pending', 'processed', 'failed', 'pendiente_clasificacion'])->default('pending');
            $table->text('processing_errors')->nullable();
            $table->string('email_message_id')->nullable()->comment('Prevent reprocessing same email');
            $table->timestamps();

            $table->unique(['id_empresa', 'dte_uuid'], 'dte_documents_empresa_uuid_unique');
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('user_email_account_id')->references('id')->on('user_email_accounts')->onDelete('cascade');
            $table->index('email_message_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dte_documents');
    }
}
