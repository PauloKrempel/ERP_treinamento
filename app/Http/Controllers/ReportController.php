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

    // Método antigo de consulta direta à API (mantido se necessário para alguma view específica)
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
            $filters["searchFields"] = "approval_date:>=";
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

    private function getVExpensesApiReports(Request $request, $periodTypeConfigKey, $statusConfigKey)
    {
        $settings = IntegrationSetting::all()->keyBy("key");
        $statusStringToProcess = $settings[$statusConfigKey]->value ?? "APROVADO"; 
        $includesValue = $settings["VEXPENSES_API_INCLUDES"]->value ?? "users,expenses";
        $includes = !empty($includesValue) ? explode(",", $includesValue) : ["users", "expenses"];
        if (!in_array("expenses", $includes)) $includes[] = "expenses";
        if (!in_array("users", $includes)) $includes[] = "users";

        $filters = ["status_string" => $statusStringToProcess];
        $periodType = $settings[$periodTypeConfigKey]->value ?? "all_time"; 
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
            $filters["search"] = "approval_date:{$calculatedStartDate},{$calculatedEndDate}"; // Usando approval_date para filtro
            $filters["searchFields"] = "approval_date:between";
        } elseif ($calculatedStartDate) {
            $filters["search"] = "approval_date:{$calculatedStartDate}";
            $filters["searchFields"] = "approval_date:>=";
        }
        if (isset($filters["searchFields"])) {
            $filters["searchJoin"] = "and";
        }
        
        Log::info("Buscando relatórios da API VExpenses com filtros:", ["filters" => $filters, "includes" => $includes, "period_type_key" => $periodTypeConfigKey, "status_key" => $statusConfigKey]);
        return $this->vexpensesService->getReports($filters, $includes);
    }

    public function fetchNewFromVExpenses(Request $request)
    {
        try {
            $reportsData = $this->getVExpensesApiReports($request, "VEXPENSES_FETCH_NEW_PERIOD_TYPE", "VEXPENSES_REPORT_STATUS_TO_IMPORT");

            if (!$reportsData || !isset($reportsData["data"])) {
                $errorMsg = "Não foi possível buscar novos relatórios do VExpenses.";
                if(isset($reportsData["message"])) $errorMsg .= " Detalhe API: " . $reportsData["message"];
                if(isset($reportsData["success"]) && $reportsData["success"] === false && empty($reportsData["message"])) $errorMsg .= " A API VExpenses indicou uma falha sem mensagem adicional.";
                
                Log::warning("Falha ao buscar novos relatórios do VExpenses ou resposta inesperada.", ["response" => $reportsData]);
                return redirect()->route("financial_reports.index")->with("error_detailed", [
                    "title" => "Falha ao Buscar Novos Relatórios",
                    "details" => [$errorMsg]
                ]);
            }
            
            if (empty($reportsData["data"])) {
                 return redirect()->route("financial_reports.index")->with("success_detailed", [
                    "title" => "Nenhum Novo Relatório Encontrado",
                    "details" => ["Não há novos relatórios no VExpenses com os critérios configurados para importação."]
                ]);
            }

            $newlyImportedCount = 0;
            $alreadyExistedCount = 0;
            $newExpensesSavedCount = 0;
            $skippedForMissingDataCount = 0;

            foreach ($reportsData["data"] as $vexpensesReportData) {
                $vexpensesReport = (array) $vexpensesReportData;
                $reportIdFromApi = $vexpensesReport["id"] ?? null;

                if (!$reportIdFromApi) {
                    Log::warning("Relatório da API VExpenses sem ID. Pulando.", ["report_data" => $vexpensesReportData]);
                    $skippedForMissingDataCount++;
                    continue;
                }

                $reportDateFromApi = $vexpensesReport["approval_date"] ?? ($vexpensesReport["created_at"] ?? null);
                if (empty($reportDateFromApi)) {
                    Log::warning("Relatório da API VExpenses ID {$reportIdFromApi} sem 'approval_date' ou 'created_at'. Pulando.", ["report_data" => $vexpensesReportData]);
                    $skippedForMissingDataCount++;
                    continue;
                }

                $existingReport = FinancialReport::where("vexpenses_report_id", $reportIdFromApi)->first();

                if ($existingReport) {
                    $alreadyExistedCount++;
                    continue; 
                }

                $localUser = null;
                $vexpensesUserIntegrationIdFromApi = null;
                $userField = $vexpensesReport["user"] ?? null;
                if ($userField) {
                    $userDataField = $userField->data ?? ($userField["data"] ?? null);
                    if ($userDataField) {
                        $integrationIdValue = $userDataField->integration_id ?? ($userDataField["integration_id"] ?? null);
                        if ($integrationIdValue) {
                            $vexpensesUserIntegrationIdFromApi = $integrationIdValue;
                            $localUser = User::where("vexpenses_id", $vexpensesUserIntegrationIdFromApi)->first();
                        }
                    }
                }

                $calculatedAmount = 0;
                $expensesField = $vexpensesReport["expenses"] ?? null;
                $expensesDataField = null;
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
                
                $newReport = FinancialReport::create([
                    "vexpenses_report_id" => $reportIdFromApi,
                    "description" => $vexpensesReport["description"] ?? "Sem descrição",
                    "amount" => $calculatedAmount,
                    "report_date" => Carbon::parse($reportDateFromApi)->toDateString(),
                    "status" => $vexpensesReport["status"] ?? "Importado",
                    "origin" => "VExpenses",
                    "user_id" => $localUser ? $localUser->id : null,
                    "vexpenses_user_integration_id" => $vexpensesUserIntegrationIdFromApi,
                ]);
                $newlyImportedCount++;
                Log::info("Novo relatório importado do VExpenses", ["local_id" => $newReport->id, "vexpenses_id" => $reportIdFromApi]);

                if ($expensesDataField && is_array($expensesDataField)) {
                    foreach ($expensesDataField as $expenseItem) {
                        $expense = (array) $expenseItem;
                        $expenseDateFromApi = $expense["date"] ?? ($expense["created_at"] ?? null);
                        if (empty($expenseDateFromApi)) {
                             Log::warning("Despesa do relatório VExpenses ID {$reportIdFromApi} sem 'date' ou 'created_at'. Usando data atual.", ["expense_data" => $expense]);
                             $expenseDateFromApi = Carbon::now()->toDateTimeString();
                        }

                        FinancialReportExpense::create([
                            "financial_report_id" => $newReport->id,
                            "vexpenses_expense_id" => $expense["id"] ?? null,
                            "title" => $expense["title"] ?? "Despesa sem título",
                            "value" => (float)($expense["value"] ?? 0),
                            "date" => Carbon::parse($expenseDateFromApi)->toDateString(), // Corrected from expense_date
                            "observation" => $expense["observation"] ?? null,
                            "receipt_url" => $expense["reicept_url"] ?? ($expense["receipt_url"] ?? null),
                        ]);
                        $newExpensesSavedCount++;
                    }
                }
            }
            
            $successMessage = "Busca de Novos Relatórios Concluída. Novos relatórios importados: {$newlyImportedCount}.";
            if ($alreadyExistedCount > 0) {
                $successMessage .= " Relatórios já existentes e ignorados: {$alreadyExistedCount}.";
            }
            if ($skippedForMissingDataCount > 0) {
                Log::info("Relatórios da API pulados por falta de dados essenciais durante fetchNew: {$skippedForMissingDataCount}");
            }

            return redirect()->route("financial_reports.index")->with("success_detailed", [
                "title" => "Busca de Novos Relatórios",
                "details" => [$successMessage]
            ]);

        } catch (\Exception $e) {
            Log::error("Erro ao buscar novos relatórios do VExpenses: " . $e->getMessage(), ["trace" => $e->getTraceAsString()]);
            return redirect()->route("financial_reports.index")->with("error_detailed", [
                "title" => "Erro Interno ao Buscar Novos Relatórios",
                "details" => ["Ocorreu um erro inesperado. Detalhes: " . $e->getMessage()]
            ]);
        }
    }

    public function updateExistingFromVExpenses(Request $request)
    {
        try {
            $reportsData = $this->getVExpensesApiReports($request, "VEXPENSES_UPDATE_PERIOD_TYPE", "VEXPENSES_REPORT_STATUS_TO_UPDATE"); 

            if (!$reportsData || !isset($reportsData["data"])) {
                $errorMsg = "Não foi possível buscar relatórios do VExpenses para atualização.";
                if(isset($reportsData["message"])) $errorMsg .= " Detalhe API: " . $reportsData["message"];
                if(isset($reportsData["success"]) && $reportsData["success"] === false && empty($reportsData["message"])) $errorMsg .= " A API VExpenses indicou uma falha sem mensagem adicional.";

                Log::warning("Falha ao buscar relatórios do VExpenses para atualização ou resposta inesperada.", ["response" => $reportsData]);
                return redirect()->route("financial_reports.index")->with("error_detailed", [
                    "title" => "Falha ao Buscar Relatórios para Atualização",
                    "details" => [$errorMsg]
                ]);
            }
            
            if (empty($reportsData["data"])) {
                 return redirect()->route("financial_reports.index")->with("success_detailed", [
                    "title" => "Nenhum Relatório Encontrado para Atualizar",
                    "details" => ["Não há relatórios no VExpenses com os critérios configurados para atualização, ou nenhum deles existe localmente para ser modificado."]
                ]);
            }

            $updatedCount = 0;
            $notFoundLocallyCount = 0;
            $noChangesCount = 0;
            $updatedExpensesCount = 0;
            $skippedForMissingDataCount = 0;
            $skippedReportsDetails = [];

            foreach ($reportsData["data"] as $vexpensesReportData) {
                $vexpensesReport = (array) $vexpensesReportData;
                $reportIdFromApi = $vexpensesReport["id"] ?? null;

                if (!$reportIdFromApi) {
                    Log::warning("Relatório da API VExpenses sem ID durante atualização. Pulando.", ["report_data" => $vexpensesReportData]);
                    $skippedForMissingDataCount++;
                    $skippedReportsDetails[] = $vexpensesReportData;
                    continue;
                }

                $reportDateFromApi = $vexpensesReport["approval_date"] ?? ($vexpensesReport["created_at"] ?? null);
                if (empty($reportDateFromApi)) {
                    Log::warning("Relatório da API VExpenses ID {$reportIdFromApi} sem 'approval_date' ou 'created_at' durante atualização. Pulando.", ["report_data" => $vexpensesReportData]);
                    $skippedForMissingDataCount++;
                    $skippedReportsDetails[] = $vexpensesReportData;
                    continue;
                }
                $reportDateFromApi = Carbon::parse($reportDateFromApi)->toDateString();

                $existingReport = FinancialReport::where("vexpenses_report_id", $reportIdFromApi)->first();

                if (!$existingReport) {
                    $notFoundLocallyCount++;
                    continue;
                }

                $localUser = $existingReport->user; 
                $vexpensesUserIntegrationIdFromApi = $existingReport->vexpenses_user_integration_id;
                $userField = $vexpensesReport["user"] ?? null;
                if ($userField) {
                    $userDataField = $userField->data ?? ($userField["data"] ?? null);
                    if ($userDataField) {
                        $integrationIdValue = $userDataField->integration_id ?? ($userDataField["integration_id"] ?? null);
                        if ($integrationIdValue) {
                            $vexpensesUserIntegrationIdFromApi = $integrationIdValue;
                            $foundUser = User::where("vexpenses_id", $vexpensesUserIntegrationIdFromApi)->first();
                            if ($foundUser) $localUser = $foundUser;
                        }
                    }
                }

                $calculatedAmount = 0;
                $expensesField = $vexpensesReport["expenses"] ?? null;
                $expensesDataField = null;
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

                $updateData = [
                    "description" => $vexpensesReport["description"] ?? $existingReport->description,
                    "amount" => $calculatedAmount, 
                    "report_date" => $reportDateFromApi,
                    "status" => $vexpensesReport["status"] ?? $existingReport->status,
                    "user_id" => $localUser ? $localUser->id : $existingReport->user_id,
                    "vexpenses_user_integration_id" => $vexpensesUserIntegrationIdFromApi,
                ];

                $changed = false;
                foreach ($updateData as $key => $value) {
                    if ($existingReport->{$key} != $value) {
                        $changed = true;
                        break;
                    }
                }

                if ($changed) {
                    $existingReport->update($updateData);
                    $updatedCount++;
                    Log::info("Relatório local atualizado com dados do VExpenses", ["local_id" => $existingReport->id, "vexpenses_id" => $reportIdFromApi, "changes" => $updateData]);
                } else {
                    $noChangesCount++;
                }

                if ($expensesDataField && is_array($expensesDataField)) {
                    $apiExpenseIds = array_map(function($item) { 
                        $itemArray = (array)$item;
                        return $itemArray["id"] ?? null; 
                    }, $expensesDataField);
                    $apiExpenseIds = array_filter($apiExpenseIds);

                    $localExpensesCollection = FinancialReportExpense::where("financial_report_id", $existingReport->id)->get();
                    $localExpensesKeyedById = [];
                    foreach($localExpensesCollection as $le) {
                        if($le->vexpenses_expense_id) $localExpensesKeyedById[$le->vexpenses_expense_id] = $le;
                    }

                    foreach ($expensesDataField as $expenseItem) {
                        $expense = (array) $expenseItem;
                        $apiExpenseId = $expense["id"] ?? null;
                        if (!$apiExpenseId) continue;

                        $expenseDateFromApi = $expense["date"] ?? ($expense["created_at"] ?? null);
                        if (empty($expenseDateFromApi)) {
                             $expenseDateFromApi = $reportDateFromApi;
                        }

                        $expenseDataToSave = [
                            "financial_report_id" => $existingReport->id,
                            "vexpenses_expense_id" => $apiExpenseId,
                            "title" => $expense["title"] ?? "Despesa sem título",
                            "value" => (float)($expense["value"] ?? 0),
                            "date" => Carbon::parse($expenseDateFromApi)->toDateString(), // Corrected from expense_date
                            "observation" => $expense["observation"] ?? null,
                            "receipt_url" => $expense["reicept_url"] ?? ($expense["receipt_url"] ?? null),
                        ];

                        if (isset($localExpensesKeyedById[$apiExpenseId])) {
                            $localExpense = $localExpensesKeyedById[$apiExpenseId];
                            $expenseChanged = false;
                            foreach($expenseDataToSave as $key => $value) {
                                if ($localExpense->{$key} != $value) {
                                    $expenseChanged = true;
                                    break;
                                }
                            }
                            if ($expenseChanged) {
                                $localExpense->update($expenseDataToSave);
                                $updatedExpensesCount++;
                            }
                        } else {
                            FinancialReportExpense::create($expenseDataToSave);
                            $updatedExpensesCount++;
                        }
                    }
                }
            }
            
            $successMessage = "Atualização de Relatórios Existentes Concluída.";
            if ($updatedCount > 0) $successMessage .= " Relatórios atualizados: {$updatedCount}.";
            if ($updatedExpensesCount > 0) $successMessage .= " Despesas sincronizadas (adicionadas/atualizadas): {$updatedExpensesCount}.";
            if ($noChangesCount > 0) $successMessage .= " Relatórios sem alterações: {$noChangesCount}.";
            if ($notFoundLocallyCount > 0) $successMessage .= " Relatórios da API não encontrados localmente: {$notFoundLocallyCount}.";
            
            if ($skippedForMissingDataCount > 0) {
                Log::info("Relatórios da API pulados por falta de dados essenciais durante updateExisting: {$skippedForMissingDataCount}", ["details" => $skippedReportsDetails]);
            }

            return redirect()->route("financial_reports.index")->with("success_detailed", [
                "title" => "Atualização de Relatórios Existentes",
                "details" => [$successMessage]
            ]);

        } catch (\Exception $e) {
            Log::error("Erro ao atualizar relatórios existentes do VExpenses: " . $e->getMessage(), ["trace" => $e->getTraceAsString()]);
            return redirect()->route("financial_reports.index")->with("error_detailed", [
                "title" => "Erro Interno ao Atualizar Relatórios",
                "details" => ["Ocorreu um erro inesperado. Detalhes: " . $e->getMessage()]
            ]);
        }
    }

    public function markReportAsPaid(Request $request, FinancialReport $financialReport)
    {
        try {
            $payload = [];
            $simulateErrorSetting = IntegrationSetting::where('key', 'VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR')->first();
            $simulateError = $simulateErrorSetting && ($simulateErrorSetting->value === 'true' || $simulateErrorSetting->value === true || $simulateErrorSetting->value === 1);

            if (!$simulateError) {
                $currentDateTime = Carbon::now()->format('Y-m-d H:i:s');
                $payload['payment_date'] = $currentDateTime;
            }

            if ($financialReport->origin === "VExpenses" && $financialReport->vexpenses_report_id) {
                $apiResponse = $this->vexpensesService->markReportAsPaid($financialReport->vexpenses_report_id, $payload);
                Log::info("Tentativa de marcar relatório como pago no VExpenses", [
                    "report_id" => $financialReport->id,
                    "vexpenses_report_id" => $financialReport->vexpenses_report_id,
                    "payload_sent" => $payload,
                    "simulate_error_setting" => $simulateError,
                    "api_response" => $apiResponse
                ]);

                if (!($apiResponse["success"] ?? false)) {
                    $errorMessage = "Falha ao marcar relatório como pago no VExpenses.";
                    if (!empty($apiResponse["message"])) {
                        $errorMessage .= " Detalhe da API: " . $apiResponse["message"];
                    }
                    
                    $financialReport->status = "Pago";
                    $financialReport->payment_date = Carbon::now()->toDateString(); 
                    $financialReport->save();

                    return redirect()->route("financial_reports.index")
                        ->with("warning_detailed", [
                            "title" => "Pago Localmente (Falha API VExpenses)",
                            "details" => [$errorMessage, "O relatório #{$financialReport->id} foi marcado como PAGO localmente, mas houve uma falha ao tentar sincronizar com o VExpenses. Verifique o status no VExpenses."]
                        ]);
                }
            }

            $financialReport->status = "Pago";
            $financialReport->payment_date = Carbon::now()->toDateString(); 
            $financialReport->save();

            return redirect()->route("financial_reports.index")->with("success_detailed", [
                "title" => "Operação Concluída",
                "details" => ["Relatório #{$financialReport->id} marcado como pago com sucesso."]
            ]);

        } catch (\Exception $e) {
            Log::error("Erro ao marcar relatório como pago: " . $e->getMessage(), ["report_id" => $financialReport->id, "trace" => $e->getTraceAsString()]);
            return redirect()->route("financial_reports.index")->with("error_detailed", [
                "title" => "Erro ao Marcar como Pago",
                "details" => ["Ocorreu um erro inesperado ao tentar marcar o relatório #{$financialReport->id} como pago. Detalhes: " . $e->getMessage()]
            ]);
        }
    }

    public function getExpensesData(Request $request, FinancialReport $financialReport)
    {
        try {
            $expenses = FinancialReportExpense::where('financial_report_id', $financialReport->id)->get();
            
            Log::info('Array de despesas completo para o relatório ID: ' . $financialReport->id, ['expenses_array' => $expenses->toArray()]);

            return response()->json([
                'success' => true,
                'data' => $expenses
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error("Relatório financeiro não encontrado ao buscar despesas: " . $e->getMessage(), ["financial_report_id" => $financialReport->id ?? $request->input('report_id')]);
            return response()->json([
                'success' => false,
                'message' => 'Relatório financeiro não encontrado.'
            ], 404);
        } catch (\Exception $e) {
            Log::error("Erro ao buscar despesas do relatório: " . $e->getMessage(), ["financial_report_id" => $financialReport->id ?? $request->input('report_id'), "exception" => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Ocorreu um erro ao buscar as despesas do relatório.'
            ], 500);
        }
    }
}

