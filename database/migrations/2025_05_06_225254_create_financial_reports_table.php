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
        Schema::create('financial_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // ID do usuário no nosso ERP
            $table->string('vexpenses_report_id')->nullable()->unique(); // ID do relatório no VExpenses, se aplicável
            $table->string('vexpenses_user_integration_id')->nullable(); // Código de integração do usuário no VExpenses
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('report_date'); // Data do relatório
            $table->string('status'); // Ex: Pendente, Aprovado, Pago, Rejeitado, Importado
            $table->string('origin'); // Ex: VExpenses, Manual
            $table->timestamp('payment_date')->nullable(); // Data do pagamento fictício
            $table->text('notes')->nullable(); // Observações adicionais
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_reports');
    }
};
