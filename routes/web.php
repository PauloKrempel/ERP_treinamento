<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\IntegrationSettingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    // Redirect to a dashboard or a relevant starting page later
    // return redirect()->route('reports.index'); // Old direct VExpenses reports
    return redirect()->route('financial_reports.index'); // New local reports page
});

Route::resource('cost_centers', CostCenterController::class);
Route::resource('projects', ProjectController::class);
Route::resource('users', UserController::class);

// VExpenses Direct API Report Viewer (can be kept for direct checking or removed)
Route::get('/vexpenses-reports-direct', [ReportController::class, 'index'])->name('vexpenses.reports.direct');
// Route to trigger import from VExpenses
Route::post('/vexpenses-reports/import', [ReportController::class, 'importFromVExpenses'])->name('vexpenses.reports.import');

// New "Contas a Pagar" (Financial Reports) section
// This will list reports from our local financial_reports table
Route::get('/financial-reports', [FinancialReportController::class, 'index'])->name('financial_reports.index');
Route::post('/financial-reports/{financialReport}/pay', [ReportController::class, 'markAsPaid'])->name('financial_reports.markAsPaid'); // Re-using markAsPaid from ReportController for now, might move to FinancialReportController
Route::get('/financial-reports/create', [FinancialReportController::class, 'create'])->name('financial_reports.create');
Route::post('/financial-reports', [FinancialReportController::class, 'store'])->name('financial_reports.store');
Route::get('/financial-reports/{report}/expenses', [ReportController::class, 'getExpensesData'])->name('financial_reports.expenses.data'); // Rota para buscar despesas

// Integration Settings
Route::resource('integration-settings', IntegrationSettingController::class)->only(['index', 'edit', 'update']);

Route::post('/vexpenses/reports/import', [ReportController::class, 'fetchNewFromVExpenses'])->name('vexpenses.reports.import');
Route::post('/vexpenses/reports/update-existing', [ReportController::class, 'updateExistingFromVExpenses'])->name('vexpenses.reports.updateExisting');



// Basic Auth routes (if needed, Laravel Breeze or Jetstream can be installed)
// For now, we'll focus on the core CRUDs without authentication for simplicity.

// require __DIR__.'/auth.php'; // Commented out as auth.php does not exist and is not needed for now


