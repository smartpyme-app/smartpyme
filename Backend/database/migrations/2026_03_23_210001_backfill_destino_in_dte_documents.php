<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class BackfillDestinoInDteDocuments extends Migration
{
    public function up(): void
    {
        $mapeos = DB::table('dte_tipo_mapeo')->where('activo', true)->pluck('destino', 'codigo_mh');
        $docs = DB::table('dte_documents')->whereNull('destino')->get();

        foreach ($docs as $doc) {
            $destino = $mapeos[$doc->dte_type] ?? 'compra';
            DB::table('dte_documents')->where('id', $doc->id)->update(['destino' => $destino]);
        }
    }

    public function down(): void
    {
        // No op - we don't want to null out destino on rollback
    }
}
