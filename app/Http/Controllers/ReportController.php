<?php

namespace App\Http\Controllers;

use App\Services\VExpensesService;
use App\Models\FinancialReport;
use App\Models\User;
use App\Models\IntegrationSetting;
use App\Models\FinancialReportExpense; // Adicionar o model da despesa
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
        // ... (código do index mantido como antes)
        $filters = [];
        if ($request->filled("status_string")) {
            $filters["status_string"] = $request->input("status_string");
        }
        if ($request->filled("start_date")) {
            $filters["start_date"] = $request->input("start_date");
        }
        if ($request->filled("end_date")) {
            $filters["end_date"] = $request->input("end_date");
        }

        $defaultIncludesSetting = IntegrationSetting::where("key", "VEXPENSES_API_INCLUDES")->first();
        // Garantir que expenses e users sejam incluídos para a visualização direta, se necessário
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
            $statusSetting = IntegrationSetting::where("key", "VEXPENSES_REPORT_STATUS_TO_IMPORT")->first();
            $includesSetting = IntegrationSetting::where("key", "VEXPENSES_API_INCLUDES")->first();

            $statusStringToImport = $statusSetting ? $statusSetting->value : "APROVADO";
            $includes = $includesSetting ? explode(",", $includesSetting->value) : ["users", "expenses"];
            if (!in_array("expenses", $includes)) {
                $includes[] = "expenses";
            }
            if (!in_array("users", $includes)) {
                $includes[] = "users";
            }
            
            $filters = ["status_string" => $statusStringToImport];

            if ($request->filled("import_start_date")) {
                $filters["start_date"] = $request->input("import_start_date");
            }
            if ($request->filled("import_end_date")) {
                $filters["end_date"] = $request->input("import_end_date");
            }

            $reportsData = $this->vexpensesService->getReports($filters, $includes);

            if (!$reportsData || !isset($reportsData["data"])) {
                Log::warning("Nenhum dado recebido da API VExpenses ou erro durante a importação.", [
                    "response" => $reportsData,
                    "filters_used" => $filters,
                    "includes_used" => $includes
                ]);
                return redirect()->back()->with("error", "Não foi possível buscar relatórios do VExpenses ou nenhum relatório encontrado com os filtros de importação atuais.");
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
                $currentFinancialReportId = null;

                // Calcular valor total somando as despesas
                $expensesField = $vexpensesReport["expenses"] ?? null;
                if ($expensesField) {
                    $expensesDataField = null;
                    if (is_object($expensesField) && isset($expensesField->data)) {
                        $expensesDataField = $expensesField->data;
                    } elseif (is_array($expensesField) && isset($expensesField["data"])) {
                        $expensesDataField = $expensesField["data"];
                    }
                    if ($expensesDataField && is_array($expensesDataField)) {
                        foreach ($expensesDataField as $expenseItem) {
                            $expense = (array) $expenseItem;
                            if (isset($expense["value"])) {
                                $calculatedAmount += (float)$expense["value"];
                            }
                        }
                    }
                }
                
                // Lógica para buscar usuário
                $userField = $vexpensesReport["user"] ?? null;
                if ($userField) {
                    $userDataField = null;
                    if (is_object($userField) && isset($userField->data)) {
                        $userDataField = $userField->data;
                    } elseif (is_array($userField) && isset($userField["data"])) {
                        $userDataField = $userField["data"];
                    }
                    if ($userDataField) {
                        $integrationIdValue = null;
                        if (is_object($userDataField) && isset($userDataField->integration_id)) {
                            $integrationIdValue = $userDataField->integration_id;
                        } elseif (is_array($userDataField) && isset($userDataField["integration_id"])) {
                            $integrationIdValue = $userDataField["integration_id"];
                        }
                        if ($integrationIdValue) {
                            $vexpensesUserIntegrationIdFromApi = $integrationIdValue;
                        }
                    }
                }

                $reportToProcess = null; // Para armazenar o relatório local após criação/atualização

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
                        if (!$reportNeedsSave) $updatedCount++; // Contar apenas se não houve outra alteração que já contaria
                    }                    
                    if ($existingReport->amount != $calculatedAmount) {
                        $existingReport->amount = $calculatedAmount;
                        $reportNeedsSave = true;
                        if (!$reportNeedsSave) $updatedCount++; // Contar apenas se não houve outra alteração
                    }
                    if ($reportNeedsSave) {
                         if ($updatedCount == 0 || ($updatedCount > 0 && $existingReport->wasChanged())) $updatedCount++;
                        $existingReport->save();
                    } else {
                        $skippedCount++;
                    }
                } else {
                    // Criar novo relatório
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

                // Salvar despesas individuais se o relatório foi processado (criado ou atualizado)
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
                                    "receipt_url" => $expense["reicept_url"] ?? null, // Atenção ao typo "reicept_url"
                                    "observation" => $expense["observation"] ?? null
                                ]
                            );
                            $newExpensesSavedCount++; // Pode contar todas as despesas processadas ou apenas as novas/atualizadas
                        }
                    }
                }
            }

            $message = [];
            if ($importedCount > 0) $message[] = "{$importedCount} relatório(s) novo(s) importado(s).";
            if ($updatedCount > 0) $message[] = "{$updatedCount} relatório(s) existente(s) foram atualizado(s).";
            if ($newExpensesSavedCount > 0) $message[] = "{$newExpensesSavedCount} despesa(s) individual(is) foram salvas/atualizadas.";
            if ($skippedCount > 0 && $updatedCount == 0 && $importedCount == 0) $message[] = "{$skippedCount} relatório(s) já existiam e não precisaram de atualização.";
            if (empty($message)) $message[] = "Nenhuma operação de importação ou atualização realizada nos relatórios.";
            
            return redirect()->route("financial_reports.index")->with("success", implode(" ", $message));

        } catch (\Exception $e) {
            Log::error("Erro ao importar relatórios do VExpenses: " . $e->getMessage(), ["exception" => $e, "trace" => $e->getTraceAsString()]);
            return redirect()->back()->with("error", "Ocorreu um erro ao importar os relatórios: " . $e->getMessage());
        }
    }

    public function markAsPaid(Request $request, $localReportId)
    {
        // ... (código do markAsPaid mantido como antes)
        $localReport = FinancialReport::find($localReportId);
        if (!$localReport) {
            return redirect()->back()->with("error", "Relatório local não encontrado.");
        }
        if ($localReport->origin === "VExpenses" && $localReport->vexpenses_report_id) {
            $updateResult = $this->vexpensesService->markReportAsPaid($localReport->vexpenses_report_id);
            if ($updateResult === null) {
                Log::error("Falha ao marcar relatório como Pago na API VExpenses.", ["vexpenses_report_id" => $localReport->vexpenses_report_id]);
                return redirect()->back()->with("error", "Falha ao marcar o relatório como pago no VExpenses.");
            }
        }
        $localReport->status = "Pago";
        $localReport->payment_date = now();
        $localReport->save();
        return redirect()->route("financial_reports.index")->with("success", "Relatório marcado como pago.");
    }
}

