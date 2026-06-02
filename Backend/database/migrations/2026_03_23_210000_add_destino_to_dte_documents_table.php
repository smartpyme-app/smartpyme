<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDestinoToDteDocumentsTable extends Migration
{
    public function up(): void
    {
        Schema::table('dte_documents', function (Blueprint $table) {
            $table->string('destino', 20)->nullable()->after('processing_errors')
                ->comment('compra|gasto - override from dte_tipo_mapeo, user can change');
        });
    }

    public function down(): void
    {
        Schema::table('dte_documents', function (Blueprint $table) {
            $table->dropColumn('destino');
        });
    }
}
