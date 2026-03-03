<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Guarda el historial de comisiones de la plataforma por proyecto
     */
    public function up(): void
    {
        Schema::create('platform_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('developer_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('total_amount', 12, 2); // Monto total del proyecto
            $table->decimal('held_amount', 12, 2); // Monto retenido (50%)
            $table->decimal('commission_rate', 5, 2); // Tasa de comisión (15% o 20%)
            $table->decimal('commission_amount', 12, 2); // Monto de comisión cobrada
            $table->decimal('net_amount', 12, 2); // Monto neto para el developer
            $table->enum('status', ['pending', 'released', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_commissions');
    }
};
