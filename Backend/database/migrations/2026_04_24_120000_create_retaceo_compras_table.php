<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Una compra solo puede vincularse a un retaceo (índice único en id_compra).
     */
    public function up(): void
    {
        Schema::create('retaceo_compras', function (Blueprint $table) {
            $table->increments('id');
            // Mismo tipo que retaceos.id / compras.id (INT signed en esta base)
            $table->integer('id_retaceo');
            $table->integer('id_compra');
            $table->timestamps();

            $table->unique(['id_retaceo', 'id_compra']);
            $table->unique('id_compra');

            $table->foreign('id_retaceo')->references('id')->on('retaceos')->onDelete('cascade');
            $table->foreign('id_compra')->references('id')->on('compras')->onDelete('restrict');
        });

        foreach (DB::table('retaceos')->orderBy('id')->cursor() as $row) {
            if (empty($row->id_compra)) {
                continue;
            }
            DB::table('retaceo_compras')->insert([
                'id_retaceo' => $row->id,
                'id_compra' => $row->id_compra,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retaceo_compras');
    }
};
