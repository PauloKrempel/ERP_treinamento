<?php

namespace App\Http\Controllers;

use App\Services\VExpensesService;
use App\Models\FinancialReport;
use App\Models\User;
use App\Models\IntegrationSetting;
use App\Models\FinancialReportExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected $vexpensesService;

    public function __construct(VExpensesService $vexpensesService)
    {
        $this->vexpensesService = $vexpensesService;
    }

    public function index(Request $request)
    {
        $filters = [];
        if ($request->filled("status_string")) {
            $filters["status_string"] = $request->input("status_string");
        }
        
        if ($request->filled("start_date") && $request->filled("end_date")) {
            $filters["search"] = "approval_date:{$request->input("start_date")},{$request->input("end_date")}";
            $filters["searchFields"] = "approval_date:between";
        } elseif ($request->filled("start_date")) {
            $filters["search"] = "approval_date:{$request->input("start_date")}";
            $filters["searchFields"] = "approval_date:>="; // Assuming >= for a single start date
        }
        if (isset($filters["searchFields"])){
            $filters["searchJoin"] = "and";
        }

        $defaultIncludesSetting = IntegrationSetting::where("key", "VEXPENSES_API_INCLUDES")->first();
        $includes = $defaultIncludesSetting ? explode(",", $defaultIncludesSetting->value) : ["users", "expenses"]; 
        if (!in_array("expenses", $includes)) {
            $includes[] = "expenses";
        }
        if (!in_array("users", $includes)) {
            $includes[] = "users";
        }

        $reportsData = $this->vexpensesService->getReports($filters, $includes);

        $reports = [];
        if ($reportsData && isset($reportsData["data"])) {
            $reports = $reportsData["data"];
        }

        $statusOptions = [
            "ABERTO" => "ABERTO",
            "APROVADO" => "APROVADO",
            "PAGO" => "PAGO",
            "REPROVADO" => "REPROVADO",
            "ENVIADO" => "ENVIADO",
            "REABERTO" => "REABERTO"
        ];

        return view("reports.index", compact("reports", "statusOptions", "filters"))
            ->with("pageTitle", "Relatórios VExpenses (Consulta Direta API)");
    }

    public function importFromVExpenses(Request $request)
    {
        try {
            $settings = IntegrationSetting::all()->keyBy("key");

            $statusStringToImport = $settings["VEXPENSES_REPORT_STATUS_TO_IMPORT"]->value ?? "APROVADO";
            $includesValue = $settings["VEXPENSES_API_INCLUDES"]->value ?? "users,expenses";
            $includes = !empty($includesValue) ? explode(",", $includesValue) : ["users", "expenses"];
            
            if (!in_array("expenses", $includes)) {
                $includes[] = "expenses";
            }
            if (!in_array("users", $includes)) {
                $includes[] = "users";
            }
            
            $filters = ["status_string" => $statusStringToImport];

            $periodType = $settings["VEXPENSES_IMPORT_PERIOD_TYPE"]->value ?? "all_time";
            $customStartDate = $settings["VEXPENSES_IMPORT_CUSTOM_START_DATE"]->value ?? null;
            $customEndDate = $settings["VEXPENSES_IMPORT_CUSTOM_END_DATE"]->value ?? null;

            $calculatedStartDate = null;
            $calculatedEndDate = null;

            switch ($periodType) {
                case "last_24_hours":
                    $calculatedStartDate = Carbon::now()->subDay()->startOfDay()->toDateString();
                    $calculatedEndDate = Carbon::now()->endOfDay()->toDateString();
                    break;
                case "last_7_days":
                    $calculatedStartDate = Carbon::now()->subDays(6)->startOfDay()->toDateString();
                    $calculatedEndDate = Carbon::now()->endOfDay()->toDateString();
                    break;
                case "last_15_days":
                    $calculatedStartDate = Carbon::now()->subDays(14)->startOfDay()->toDateString();
                    $calculatedEndDate = Carbon::now()->endOfDay()->toDateString();
                    break;
                case "last_30_days":
                    $calculatedStartDate = Carbon::now()->subDays(29)->startOfDay()->toDateString();
                    $calculatedEndDate = Carbon::now()->endOfDay()->toDateString();
                    break;
                case "custom":
                    if ($customStartDate && $customEndDate) {
                        $calculatedStartDate = Carbon::parse($customStartDate)->toDateString();
                        $calculatedEndDate = Carbon::parse($customEndDate)->toDateString();
                    }
                    break;
                case "all_time":
                default:
                    break;
            }

            if ($calculatedStartDate && $calculatedEndDate) {
                $filters["search"] = "approval_date:{$calculatedStartDate},{$calculatedEndDate}";
                $filters["searchFields"] = "approval_date:between";
            } elseif ($calculatedStartDate) { 
                $filters["search"] = "approval_date:{$calculatedStartDate}";
                $filters["searchFields"] = "approval_date:>=";
            }
            if (isset($filters["searchFields"])) {
                $filters["searchJoin"] = "and";
            }

            $reportsData = $this->vexpensesService->getReports($filters, $includes);

            if (!$reportsData || !isset($reportsData["data"]) || (isset($reportsData["success"]) && !$reportsData["success"])) {
                $errorMsg = "Não foi possível buscar relatórios do VExpenses.";
                if(isset($reportsData["message"])) $errorMsg .= " Detalhe: " . $reportsData["message"];
                Log::warning("Nenhum dado recebido da API VExpenses ou erro durante a importação.", [
                    "response" => $reportsData,
                    "filters_used" => $filters,
                    "includes_used" => $includes
                ]);
                return redirect()->route("financial_reports.index")->with("error", $errorMsg);
            }

            $importedCount = 0;
            $skippedCount = 0;
            $updatedCount = 0;
            $newExpensesSavedCount = 0;

            foreach ($reportsData["data"] as $vexpensesReportData) {
                $vexpensesReport = (array) $vexpensesReportData;
                $reportIdFromApi = $vexpensesReport["id"];

                $existingReport = FinancialReport::where("vexpenses_report_id", $reportIdFromApi)->first();
                $localUser = null;
                $vexpensesUserIntegrationIdFromApi = null;
                $reportNeedsSave = false;
                $calculatedAmount = 0;

                $expensesField = $vexpensesReport["expenses"] ?? null;
                if ($expensesField) {
                    $expensesDataField = $expensesField->data ?? ($expensesField["data"] ?? null);
                    if ($expensesDataField && is_array($expensesDataField)) {
                        foreach ($expensesDataField as $expenseItem) {
                            $expense = (array) $expenseItem;
                            if (isset($expense["value"])) {
                                $calculatedAmount += (float)$expense["value"];
                            }
                        }
                    }
                }
                
                $userField = $vexpensesReport["user"] ?? null;
                if ($userField) {
                    $userDataField = $userField->data ?? ($userField["data"] ?? null);
                    if ($userDataField) {
                        $integrationIdValue = $userDataField->integration_id ?? ($userDataField["integration_id"] ?? null);
                        if ($integrationIdValue) {
                            $vexpensesUserIntegrationIdFromApi = $integrationIdValue;
                        }
                    }
                }

                $reportToProcess = null;

                if ($existingReport) {
                    $reportToProcess = $existingReport;
                    if (is_null($existingReport->vexpenses_user_integration_id) && $vexpensesUserIntegrationIdFromApi) {
                        $existingReport->vexpenses_user_integration_id = $vexpensesUserIntegrationIdFromApi;
                        $reportNeedsSave = true;
                    }
                    $idToSearchUser = $existingReport->vexpenses_user_integration_id ?? $vexpensesUserIntegrationIdFromApi;
                    if ($idToSearchUser) {
                        $localUser = User::where("vexpenses_id", $idToSearchUser)->first();
                    }
                    if (is_null($existingReport->user_id) && $localUser) {
                        $existingReport->user_id = $localUser->id;
                        $reportNeedsSave = true;
                    }                    
                    if ($existingReport->amount != $calculatedAmount) {
                        $existingReport->amount = $calculatedAmount;
                        $reportNeedsSave = true;
                    }
                    if ($reportNeedsSave) {
                        $updatedCount++;
                        $existingReport->save();
                    } else {
                        $skippedCount++;
                    }
                } else {
                    if ($vexpensesUserIntegrationIdFromApi) {
                        $localUser = User::where("vexpenses_id", $vexpensesUserIntegrationIdFromApi)->first();
                    }
                    $newReport = FinancialReport::create([
                        "user_id" => $localUser ? $localUser->id : null,
                        "vexpenses_report_id" => $reportIdFromApi,
                        "vexpenses_user_integration_id" => $vexpensesUserIntegrationIdFromApi, 
                        "description" => $vexpensesReport["description"] ?? "N/A",
                        "amount" => $calculatedAmount,
                        "report_date" => Carbon::parse($vexpensesReport["report_date"] ?? $vexpensesReport["approval_date"] ?? now())->toDateString(),
                        "status" => "Importado",
                        "origin" => "VExpenses",
                        "notes" => $vexpensesReport["observation"] ?? null,
                    ]);
                    $reportToProcess = $newReport;
                    $importedCount++;
                }

                if ($reportToProcess && $expensesDataField && is_array($expensesDataField)) {
                    $currentFinancialReportId = $reportToProcess->id;
                    foreach ($expensesDataField as $expenseItem) {
                        $expense = (array) $expenseItem;
                        $vexpensesExpenseId = $expense["id"] ?? null;

                        if ($vexpensesExpenseId) {
                            FinancialReportExpense::updateOrCreate(
                                [
                                    "financial_report_id" => $currentFinancialReportId,
                                    "vexpenses_expense_id" => $vexpensesExpenseId
                                ],
                                [
                                    "title" => $expense["title"] ?? "N/A",
                                    "date" => Carbon::parse($expense["date"])->toDateString(),
                                    "value" => (float)($expense["value"] ?? 0),
                                    "receipt_url" => $expense["reicept_url"] ?? ($expense["receipt_url"] ?? null),
                                    "observation" => $expense["observation"] ?? null
                                ]
                            );
                            $newExpensesSavedCount++;
                        }
                    }
                }
            }

            $message = [];
            if ($importedCount > 0) $message[] = "{$importedCount} relatório(s) novo(s) importado(s).";
            if ($updatedCount > 0) $message[] = "{$updatedCount} relatório(s) existente(s) foram atualizado(s).";
            if ($newExpensesSavedCount > 0) $message[] = "{$newExpensesSavedCount} despesa(s) individual(is) foram salvas/atualizadas.";
            if ($skippedCount > 0 && $updatedCount == 0 && $importedCount == 0) $message[] = "{$skippedCount} relatório(s) já existiam e não precisaram de atualização.";
            if (empty($message)) $message[] = "Nenhuma operação de importação ou atualização realizada (verifique os filtros e o período selecionado).";
            
            return redirect()->route("financial_reports.index")->with("success", implode(" ", $message));

        } catch (\Exception $e) {
            Log::error("Erro ao importar relatórios do VExpenses: " . $e->getMessage(), ["exception" => $e, "trace" => $e->getTraceAsString()]);
            return redirect()->route("financial_reports.index")->with("error", "Ocorreu um erro ao importar os relatórios: " . $e->getMessage());
        }
    }

    public function markAsPaid(Request $request, $localReportId)
    {
        $localReport = FinancialReport::find($localReportId);
        if (!$localReport) {
            return redirect()->back()->with("error", "Relatório local não encontrado.");
        }

        try {
            $paymentDate = Carbon::now(); // Get current date and time

            if ($localReport->origin === "VExpenses" && $localReport->vexpenses_report_id) {
                $dataForVExpenses = [
                    'payment_date' => $paymentDate->format('Y-m-d H:i:s'), // Format as YYYY-MM-DD HH:MM:SS
                ];
                $updateResult = $this->vexpensesService->markReportAsPaid($localReport->vexpenses_report_id, $dataForVExpenses);

                if ($updateResult === null || (is_array($updateResult) && isset($updateResult['success']) && !$updateResult['success'])) {
                    $errorMessage = "Falha ao marcar o relatório como pago no VExpenses.";
                    $logContext = ["vexpenses_report_id" => $localReport->vexpenses_report_id];
                    if(is_array($updateResult) && isset($updateResult['message'])) {
                        $errorMessage .= " Detalhe: " . $updateResult['message'];
                        $logContext['service_message'] = $updateResult['message'];
                    }
                    if(is_array($updateResult) && isset($updateResult['response_body'])) {
                        $logContext['service_response_body'] = $updateResult['response_body'];
                    }
                    Log::error("Falha ao marcar relatório como Pago na API VExpenses via Service.", $logContext);
                    return redirect()->back()->with("error", $errorMessage);
                }
            }

            // Save local status and payment date
            $localReport->status = "Pago";
            $localReport->payment_date = $paymentDate; // Store the Carbon object, DB will handle format
            $localReport->save();

            return redirect()->route("financial_reports.index")->with("success", "Relatório marcado como pago com sucesso!");

        } catch (\Exception $e) {
            Log::error("Erro ao marcar relatório como pago: " . $e->getMessage(), [
                "report_id" => $localReport->id,
            ]);
            return redirect()->back()->with("error", "Ocorreu um erro ao marcar o relatório como pago: " . $e->getMessage());
        }
    }
}

