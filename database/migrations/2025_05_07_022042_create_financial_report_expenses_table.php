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
    if (!Schema::hasTable('financial_report_expenses')) {
        Schema::create('financial_report_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_report_id')->constrained('financial_reports')->onDelete('cascade');
            $table->string('title');
            $table->date('date');
            $table->decimal('value', 15, 2);
            $table->string('receipt_url')->nullable();
            $table->text('observation')->nullable();
            $table->string('vexpenses_expense_id')->nullable()->comment('ID original da despesa no VExpenses');
            $table->timestamps();
        });
    }
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("financial_report_expenses");
    }
};
