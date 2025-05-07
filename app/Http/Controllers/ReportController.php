<?php

namespace App\Http\Controllers;

use App\Services\VExpensesService;
use App\Models\FinancialReport;
use App\Models\User;
use App\Models\IntegrationSetting;
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

    /**
     * Display a listing of the VExpenses reports (current functionality - direct API view).
     * This method might need adjustment if the primary way to filter by status is always string-based.
     */
    public function index(Request $request)
    {
        $filters = [];
        $statusToQuery = null;

        // Check if a specific status_string is requested for direct view
        if ($request->filled("status_string")) {
            $filters["status_string"] = $request->input("status_string");
        } else {
            // Fallback to configured default or a general default if not filtering by specific string
            $defaultStatusSetting = IntegrationSetting::where("key", "VEXPENSES_REPORT_STATUS_TO_IMPORT")->first();
            // For direct view, we might not always want to use the import default. 
            // If no filter is applied, it fetches all. If status_id is used, it filters by that.
            if ($request->filled("status_id")) { // Keep supporting status_id for direct query if needed
                 $filters["status_id"] = $request->input("status_id");
            } else {
                // If neither status_string nor status_id is provided, don't set a default status filter
                // let it fetch all, or use a very broad default if necessary.
                // For now, if no status filter, it will fetch all reports (or as per API default)
            }
        }

        if ($request->filled("start_date")) {
            $filters["start_date"] = $request->input("start_date");
        }
        if ($request->filled("end_date")) {
            $filters["end_date"] = $request->input("end_date");
        }
        
        $defaultIncludesSetting = IntegrationSetting::where("key", "VEXPENSES_API_INCLUDES")->first();
        $includes = $defaultIncludesSetting ? explode(",", $defaultIncludesSetting->value) : ["users"];

        $reportsData = $this->vexpensesService->getReports($filters, $includes);
        
        $reports = [];
        if ($reportsData && isset($reportsData["data"])) {
            $reports = $reportsData["data"];
        }

        // Status options for the direct API view filter (can be numeric or string depending on how we want to filter here)
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

    /**
     * Imports reports from VExpenses into the local database.
     */
    public function importFromVExpenses(Request $request)
    {
        try {
            $statusSetting = IntegrationSetting::where("key", "VEXPENSES_REPORT_STATUS_TO_IMPORT")->first();
            $includesSetting = IntegrationSetting::where("key", "VEXPENSES_API_INCLUDES")->first();

            // Use string status from settings, default to "Aprovado"
            $statusStringToImport = $statusSetting ? $statusSetting->value : "APROVADO"; 
            $includes = $includesSetting ? explode(",", $includesSetting->value) : ["users"];

            // Pass status_string to the service
            $filters = ["status_string" => $statusStringToImport];
            if ($request->filled("import_start_date")) {
                $filters["start_date"] = $request->input("import_start_date");
            }
            if ($request->filled("import_end_date")) {
                $filters["end_date"] = $request->input("import_end_date");
            }

            $reportsData = $this->vexpensesService->getReports($filters, $includes);

            if (!$reportsData || !isset($reportsData["data"])) {
                Log::warning("Nenhum dado recebido da API VExpenses ou erro durante a importação.", ["response" => $reportsData, "filters_used" => $filters, "includes_used" => $includes]);
                return redirect()->back()->with("error", "Não foi possível buscar relatórios do VExpenses ou nenhum relatório encontrado com os filtros de importação atuais.");
            }

            $importedCount = 0;
            $skippedCount = 0;

            foreach ($reportsData["data"] as $vexpensesReport) {
                $existingReport = FinancialReport::where("vexpenses_report_id", $vexpensesReport["id"])->first();
                if ($existingReport) {
                    $skippedCount++;
                    continue;
                }

                //$localUserId = null;
                $vexpensesUserIntegrationId = null;
                $localUserId = null;

                if (in_array("users", $includes) && isset($vexpensesReport["user"]) && isset($vexpensesReport["user"]["integration_id"])) {
                    $vexpensesUserIntegrationId = $vexpensesReport["user"]["integration_id"];
                    $localUser = User::where("vexpenses_id", $vexpensesUserIntegrationId)->first();
                    if (!$localUser) {
                        Log::warning("Usuário do relatório VExpenses com integration_id para o relatório ID {$vexpensesReport["id"]} era '{$vexpensesUserIntegrationId}' mas não foi encontrado nos usuários locais.");
                    }
                }

                FinancialReport::create([
                    "user_id" => $localUser ? $localUser->id : null,
                    "vexpenses_report_id" => $vexpensesReport["id"],
                    "vexpenses_user_integration_id" => $vexpensesUserIntegrationId,
                    "description" => $vexpensesReport["description"] ?? "N/A",
                    "amount" => $vexpensesReport["total_amount"] ?? ($vexpensesReport["amount"] ?? 0),
                    "report_date" => Carbon::parse($vexpensesReport["report_date"])->toDateString(),
                    "status" => "Importado",
                    "origin" => "VExpenses",
                    "notes" => $vexpensesReport["observation"] ?? null,
                ]);
                $importedCount++;
            }

            $message = "{$importedCount} relatório(s) importado(s) com sucesso.";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount} relatório(s) já existiam e foram ignorados.";
            }
            return redirect()->route("financial_reports.index")->with("success", $message);

        } catch (\Exception $e) {
            Log::error("Erro ao importar relatórios do VExpenses: " . $e->getMessage(), ["exception" => $e]);
            return redirect()->back()->with("error", "Ocorreu um erro ao importar os relatórios: " . $e->getMessage());
        }
    }

    public function markAsPaid(Request $request, $localReportId)
    {
        $localReport = FinancialReport::find($localReportId);

        if (!$localReport) {
            return redirect()->back()->with("error", "Relatório local não encontrado.");
        }

        if ($localReport->origin === "VExpenses" && $localReport->vexpenses_report_id) {
            $updateResult = $this->vexpensesService->markReportAsPaid($localReport->vexpenses_report_id);
            
            if ($updateResult === null) { 
                Log::error("Falha ao marcar relatório como Pago na API VExpenses (serviço retornou nulo, indicando falha HTTP).", [
                    "vexpenses_report_id" => $localReport->vexpenses_report_id,
                ]);
                return redirect()->back()
                    ->with("error", "Falha ao marcar o relatório como pago no VExpenses. A API do VExpenses retornou um erro ou não pôde ser contatada.");
            }
        }

        $localReport->status = "Pago";
        $localReport->payment_date = now();
        $localReport->save();

        return redirect()->route("financial_reports.index")
            ->with("success", "Relatório #{$localReport->id} (VExpenses ID: {$localReport->vexpenses_report_id}) marcado como pago com sucesso.");
    }
}

