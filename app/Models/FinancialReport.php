<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\FinancialReportExpense;


class FinancialReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vexpenses_report_id',
        'vexpenses_user_integration_id',
        'description',
        'amount',
        'report_date',
        'status',
        'origin',
        'payment_date',
        'notes',
    ];

    /**
     * Get the user that owns the financial report.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function expenses()
    {
        return $this->hasMany(FinancialReportExpense::class);
    }
}
