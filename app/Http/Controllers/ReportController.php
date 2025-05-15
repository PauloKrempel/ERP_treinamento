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
                    
                    // Atualizar outros campos se necessário, como description, report_date, status da API
                    $apiStatus = $vexpensesReport["status_string"] ?? "Pendente";
                    $localStatus = $existingReport->status;
                    // Só atualiza o status local se não estiver "Pago" localmente, 
                    // para não sobrescrever um pagamento manual com um status da API.
                    if ($localStatus !== "Pago" && $apiStatus !== $localStatus) {
                        // Mapear status da API para status locais se necessário, ou usar diretamente
                        // Por enquanto, vamos assumir que o status da API pode ser usado se não for "Pago" localmente.
                        // Se o status da API for "PAGO", e localmente não, atualizamos para "Importado" e o usuário paga manualmente.
                        // Ou, se a regra for que "PAGO" na API deve refletir como "Pago" localmente, ajuste aqui.
                        // Para este exemplo, vamos manter o status local se já for "Pago", senão, usamos o da API (ou um mapeamento)
                        // Se o status da API for PAGO, e o local não, talvez o ideal seja apenas registrar, mas não mudar para PAGO automaticamente aqui.
                        // A ação de Pagar no ERP que deve fazer isso.
                        // Vamos apenas garantir que o status local reflita um estado "Importado" ou "Pendente" se não for "Pago".
                        if ($apiStatus === "APROVADO" && $localStatus !== "Pago") {
                            $existingReport->status = "Importado"; // Ou "Pendente"
                            $reportNeedsSave = true;
                        }
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

                    $reportToProcess = FinancialReport::create([
                        "vexpenses_report_id" => $reportIdFromApi,
                        "description" => $vexpensesReport["name"] ?? "Relatório VExpenses #{$reportIdFromApi}",
                        "amount" => $calculatedAmount,
                        "report_date" => isset($vexpensesReport["approval_date"]) ? Carbon::parse($vexpensesReport["approval_date"]) : Carbon::parse($vexpensesReport["created_at"]),
                        "status" => "Importado", // Status inicial para novos relatórios importados
                        "origin" => "VExpenses",
                        "user_id" => $localUser ? $localUser->id : null,
                        "vexpenses_user_integration_id" => $vexpensesUserIntegrationIdFromApi,
                        // Adicione outros campos conforme necessário
                    ]);
                    $importedCount++;
                }

                // Salvar/Atualizar Despesas
                if ($reportToProcess && $expensesField && isset($expensesDataField) && is_array($expensesDataField)) {
                    foreach ($expensesDataField as $expenseItem) {
                        $expense = (array) $expenseItem;
                        $expenseIdFromApi = $expense["id"];

                        FinancialReportExpense::updateOrCreate(
                            [
                                "financial_report_id" => $reportToProcess->id,
                                "vexpenses_expense_id" => $expenseIdFromApi
                            ],
                            [
                                "title" => $expense["name"] ?? "Despesa",
                                "expense_date" => Carbon::parse($expense["date"] ?? $reportToProcess->report_date),
                                "value" => (float)($expense["value"] ?? 0),
                                "observation" => $expense["observation"] ?? null,
                                "receipt_url" => $expense["receipt_url"] ?? null,
                                // Adicione outros campos da despesa conforme necessário
                            ]
                        );
                        $newExpensesSavedCount++; // Conta cada updateOrCreate como uma operação de despesa salva/atualizada
                    }
                }
            }

            $message = "Importação concluída. {$importedCount} novos relatórios importados, {$updatedCount} atualizados, {$skippedCount} já existentes e sem alterações. {$newExpensesSavedCount} despesas processadas.";
            return redirect()->route("financial_reports.index")->with("success", $message);

        } catch (\Exception $e) {
            Log::error("Erro durante a importação de relatórios do VExpenses", [
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);
            return redirect()->route("financial_reports.index")->with("error", "Ocorreu um erro durante a importação: " . $e->getMessage());
        }
    }

    public function markAsPaid(Request $request, FinancialReport $financialReport)
    {
        try {
            $paymentDate = Carbon::now(); // Data de pagamento atual

            // Regra de negócio: Se o relatório veio do VExpenses, tenta marcar como pago na API também
            if ($financialReport->origin === "VExpenses" && $financialReport->vexpenses_report_id) {
                $vexpensesApiData = [
                    "payment_date" => $paymentDate->toDateTimeString(), // Formato esperado pela API: YYYY-MM-DD HH:MM:SS
                ];
                
                Log::info("Tentando marcar relatório VExpenses como pago na API", [
                    "local_report_id" => $financialReport->id,
                    "vexpenses_report_id" => $financialReport->vexpenses_report_id,
                    "data_sent" => $vexpensesApiData
                ]);

                $apiResponse = $this->vexpensesService->markReportAsPaid($financialReport->vexpenses_report_id, $vexpensesApiData);

                // Verifica se a resposta da API foi bem-sucedida
                if (!$apiResponse || (isset($apiResponse["success"]) && !$apiResponse["success"])) {
                    $errorMessage = "Falha ao marcar relatório como pago na API VExpenses.";
                    if (isset($apiResponse["message"])) {
                        $errorMessage .= " Detalhe: " . $apiResponse["message"];
                    }
                    if (isset($apiResponse["errors"])) {
                        $errorMessage .= " Erros: " . json_encode($apiResponse["errors"]);
                    }
                    Log::error($errorMessage, [
                        "local_report_id" => $financialReport->id,
                        "vexpenses_report_id" => $financialReport->vexpenses_report_id,
                        "api_response" => $apiResponse
                    ]);
                    // Decisão: Se falhar na API, não marcar localmente e retornar erro?
                    // Ou marcar localmente e apenas avisar sobre a falha na API?
                    // Por segurança, se a API VExpenses é a fonte da verdade para pagamentos VExpenses,
                    // talvez seja melhor não marcar localmente se a API falhar.
                    return redirect()->route("financial_reports.index")->with("error", $errorMessage . " O status local não foi alterado.");
                }
            }

            // Atualiza o status localmente para "Pago" e registra a data de pagamento
            $financialReport->status = "Pago";
            $financialReport->payment_date = $paymentDate;
            $financialReport->save();

            Log::info("Relatório marcado como pago localmente", [
                "local_report_id" => $financialReport->id,
                "origin" => $financialReport->origin
            ]);

            return redirect()->route("financial_reports.index")->with("success", "Relatório #{$financialReport->id} marcado como pago com sucesso!");

        } catch (\Exception $e) {
            Log::error("Erro ao marcar relatório como pago", [
                "report_id" => $financialReport->id ?? null,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);
            return redirect()->route("financial_reports.index")->with("error", "Ocorreu um erro ao tentar marcar o relatório como pago. Detalhes: " . $e->getMessage());
        }
    }
}

