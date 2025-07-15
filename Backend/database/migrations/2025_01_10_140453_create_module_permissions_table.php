<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateModulePermissionsTable extends Migration
{
    public function up()
    {
        Schema::create('module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('submodule_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->enum('permission_type', ['base', 'custom'])->default('base');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('module_permissions');
    }
}