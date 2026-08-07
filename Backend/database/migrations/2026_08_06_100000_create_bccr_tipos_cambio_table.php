<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bccr_tipos_cambio', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('venta_reference_rate', 18, 5);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bccr_tipos_cambio');
    }
};
