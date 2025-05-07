<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialReportExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        "financial_report_id",
        "title",
        "date",
        "value",
        "receipt_url",
        "observation",
        "vexpenses_expense_id",
    ];

    /**
     * Get the financial report that owns the expense.
     */
    public function financialReport()
    {
        return $this->belongsTo(FinancialReport::class);
    }
}
