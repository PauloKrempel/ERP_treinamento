<?php

namespace App\Http\Controllers;

use App\Models\FinancialReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\FinancialReportExpense;
use Carbon\Carbon; // Adicionado para formatação de data

class FinancialReportController extends Controller
{
    /**
     * Display a listing of the financial reports.
     */
    public function index(Request $request)
    {
        $query = FinancialReport::with("user")->orderBy("report_date", "desc");

        // Add filters if needed, e.g., by status, origin, date range
        if ($request->filled("status")) {
            $query->where("status", $request->input("status"));
        }
        if ($request->filled("origin")) {
            $query->where("origin", $request->input("origin"));
        }
        if ($request->filled("start_date")) {
            $query->whereDate("report_date", ">=", $request->input("start_date"));
        }
        if ($request->filled("end_date")) {
            $query->whereDate("report_date", "<=", $request->input("end_date"));
        }

        $financialReports = $query->paginate(15); // Paginate results

        $statusOptions = [ // Local status options
            "Importado" => "Importado",
            "Pendente" => "Pendente",
            "Aprovado" => "Aprovado", // If manual reports can be approved locally
            "Pago" => "Pago",
            "Rejeitado" => "Rejeitado",
        ];

        $originOptions = [
            "VExpenses" => "VExpenses",
            "Manual" => "Manual",
        ];

        return view("financial_reports.index", compact("financialReports", "statusOptions", "originOptions"))
            ->with("pageTitle", "Contas a Pagar (Relatórios Financeiros)");
    }

    /**
     * Show the form for creating a new financial report.
     */
    public function create()
    {
        $users = User::orderBy("name")->get(); // For assigning user to manual report
        return view("financial_reports.create", compact("users"))
            ->with("pageTitle", "Adicionar Novo Relatório Financeiro (Manual)");
    }

    /**
     * Store a newly created financial report in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            "description" => "required|string|max:255",
            "amount" => "required|numeric|min:0.01",
            "report_date" => "required|date",
            "user_id" => "nullable|exists:users,id",
            "status" => "required|string", // e.g., Pendente, Aprovado
            "notes" => "nullable|string",
        ]);

        try {
            FinancialReport::create([
                "user_id" => $validatedData["user_id"],
                "vexpenses_report_id" => null, // Manual entry
                "vexpenses_user_integration_id" => null, // Manual entry
                "description" => $validatedData["description"],
                "amount" => $validatedData["amount"],
                "report_date" => $validatedData["report_date"],
                "status" => $validatedData["status"],
                "origin" => "Manual",
                "payment_date" => null,
                "notes" => $validatedData["notes"],
            ]);

            return redirect()->route("financial_reports.index")
                ->with("success", "Relatório financeiro manual adicionado com sucesso.");
        } catch (\Exception $e) {
            Log::error("Error creating manual financial report: " . $e->getMessage(), ["exception" => $e]);
            return redirect()->back()
                ->withInput()
                ->with("error", "Erro ao adicionar relatório financeiro manual: " . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(FinancialReport $financialReport)
    {
        // Not implemented for now, can show details if needed
        return redirect()->route("financial_reports.index");
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FinancialReport $financialReport)
    {
        // Not implemented for now, can add if manual reports need editing
        $users = User::orderBy("name")->get();
        return view("financial_reports.edit", compact("financialReport", "users"))
            ->with("pageTitle", "Editar Relatório Financeiro");
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FinancialReport $financialReport)
    {
        $validatedData = $request->validate([
            "description" => "required|string|max:255",
            "amount" => "required|numeric|min:0.01",
            "report_date" => "required|date",
            "user_id" => "nullable|exists:users,id",
            "status" => "required|string",
            "notes" => "nullable|string",
        ]);

        try {
            $financialReport->update([
                "user_id" => $validatedData["user_id"],
                "description" => $validatedData["description"],
                "amount" => $validatedData["amount"],
                "report_date" => $validatedData["report_date"],
                "status" => $validatedData["status"],
                "notes" => $validatedData["notes"],
            ]);

            return redirect()->route("financial_reports.index")
                ->with("success", "Relatório financeiro atualizado com sucesso.");
        } catch (\Exception $e) {
            Log::error("Error updating financial report: " . $e->getMessage(), ["exception" => $e]);
            return redirect()->back()
                ->withInput()
                ->with("error", "Erro ao atualizar relatório financeiro: " . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FinancialReport $financialReport)
    {
        try {
            $financialReport->delete();
            return redirect()->route("financial_reports.index")
                ->with("success", "Relatório financeiro excluído com sucesso.");
        } catch (\Exception $e) {
            Log::error("Error deleting financial report: " . $e->getMessage(), ["exception" => $e]);
            return redirect()->route("financial_reports.index")
                ->with("error", "Erro ao excluir relatório financeiro: " . $e->getMessage());
        }
    }

    /**
     * Busca e retorna as despesas de um relatório financeiro específico.
     *
     * @param  App\Models\FinancialReport  $financialReport  (Injetado pela rota)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExpenses(FinancialReport $financialReport) // Nome do método e parâmetro ajustados
    {
        // Carrega as despesas relacionadas, se já não estiverem carregadas
        $financialReport->loadMissing('expenses');

        $formattedExpenses = $financialReport->expenses->map(function ($expense) {
            return [
                "title" => $expense->title,
                "date" => Carbon::parse($expense->date)->format("d/m/Y"), // Formata a data
                "value" => number_format($expense->value, 2, ",", "."), // Formata o valor para BRL
                "receipt_url" => $expense->receipt_url,
                "observation" => $expense->observation,
            ];
        });

        return response()->json($formattedExpenses);
    }

    // O método markAsPaid foi movido para o ReportController conforme a definição da rota
    // Se precisar dele aqui para relatórios manuais SEM interação com VExpenses, precisaria de uma rota diferente.
}

