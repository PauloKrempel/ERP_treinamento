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
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Ex: VEXPENSES_REPORT_STATUS_TO_IMPORT, VEXPENSES_API_INCLUDES
            $table->text('value')->nullable(); // Ex: "3" (para status Aprovado), "users,expenses,projects"
            $table->string('name'); // Ex: "Status de Relatório para Importar do VExpenses"
            $table->text('description')->nullable(); // Explicação da configuração
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
