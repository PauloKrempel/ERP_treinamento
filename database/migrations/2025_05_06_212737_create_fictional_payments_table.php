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
        Schema::create('fictional_payments', function (Blueprint $table) {
            $table->id();
            $table->string('vexpenses_report_id')->comment('ID do relatório no VExpenses');
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ID do usuário no ERP local');
            $table->decimal('amount', 10, 2);
            $table->timestamp('payment_date')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fictional_payments');
    }
};
