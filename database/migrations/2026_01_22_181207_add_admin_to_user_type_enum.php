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
        // El valor 'admin' en el enum user_type ya fue incluido directamente
        // en la migración 2025_11_10_214135_add_user_type_to_users_table.php
        // Esta migración existe por historial pero no requiere cambios en la BD.
        // No ejecutar ningún ALTER TABLE aquí.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ver comentario en up()
    }
};
