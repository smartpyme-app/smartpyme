<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddAnuladoToDteDocumentsProcessingStatus extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE dte_documents MODIFY processing_status ENUM(
            'pending',
            'processed',
            'failed',
            'pendiente_clasificacion',
            'anulado'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down()
    {
        DB::table('dte_documents')
            ->where('processing_status', 'anulado')
            ->update(['processing_status' => 'pending']);

        DB::statement("ALTER TABLE dte_documents MODIFY processing_status ENUM(
            'pending',
            'processed',
            'failed',
            'pendiente_clasificacion'
        ) NOT NULL DEFAULT 'pending'");
    }
}
