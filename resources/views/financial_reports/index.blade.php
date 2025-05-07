@extends("layouts.app")

@section("content")
<div class="container-fluid px-2"> {{-- Reduced padding for container-fluid --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>{{ $pageTitle ?? "Contas a Pagar (Relatórios Financeiros)" }}</h1>
        <div>
            <a href="{{ route("financial_reports.create") }}" class="btn btn-success btn-sm">Adicionar Relatório Manual</a>
            <form action="{{ route("vexpenses.reports.import") }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-info btn-sm">Puxar Relatórios do VExpenses</button>
            </form>
        </div>
    </div>

    @if(session("success"))
        <div class="alert alert-success">
            {{ session("success") }}
        </div>
    @endif
    @if(session("warning"))
        <div class="alert alert-warning">
            {{ session("warning") }}
        </div>
    @endif
    @if(session("error"))
        <div class="alert alert-danger">
            {{ session("error") }}
        </div>
    @endif

    <form method="GET" action="{{ route("financial_reports.index") }}" class="mb-3">
        <div class="row gx-2 align-items-end"> {{-- Reduced gutter --}}
            <div class="col-md-auto"> {{-- Adjusted for auto width --}}
                <label for="status" class="form-label mb-1">Status Local:</label>
                <select name="status" id="status" class="form-select form-control form-control-sm">
                    <option value="">Todos</option>
                    @foreach($statusOptions as $key => $value)
                        <option value="{{ $key }}" {{ request("status") == $key ? "selected" : "" }}>{{ $value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <label for="origin" class="form-label mb-1">Origem:</label>
                <select name="origin" id="origin" class="form-select form-control form-control-sm">
                    <option value="">Todas</option>
                    @foreach($originOptions as $key => $value)
                        <option value="{{ $key }}" {{ request("origin") == $key ? "selected" : "" }}>{{ $value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="start_date" class="form-label mb-1">Data Inicial:</label>
                <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" value="{{ request("start_date") }}">
            </div>
            <div class="col-md-2">
                <label for="end_date" class="form-label mb-1">Data Final:</label>
                <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" value="{{ request("end_date") }}">
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header py-2">Lista de Relatórios</div> {{-- Reduced padding --}}
        <div class="table-responsive">
            {{-- Removed min-width to let table try to fit, but responsive will still add scroll if needed --}}
            <table class="table table-striped table-hover table-sm"> {{-- table-sm for smaller padding --}}
                <thead style="font-size: 0.9em;"> {{-- Slightly smaller font for header --}}
                    <tr>
                        <th style="width: 4%;">ID</th>
                        <th style="width: 8%;">VExp ID</th>
                        <th style="width: 25%;">Descrição</th>
                        <th style="width: 12%;">Usuário</th>
                        <th class="text-end" style="width: 9%;">Valor</th>
                        <th style="width: 9%;">Data Rel.</th>
                        <th style="width: 9%;">Status</th>
                        <th style="width: 8%;">Origem</th>
                        <th style="width: 8%;">Data Pag.</th>
                        <th style="width: 10%; text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody style="font-size: 0.9em;"> {{-- Slightly smaller font for body --}}
                    @forelse ($financialReports as $report)
                        <tr>
                            <td>{{ $report->id }}</td>
                            <td>{{ $report->vexpenses_report_id ?? "-" }}</td>
                            <td>{{ Str::limit($report->description, 35) }}</td> {{-- Limiting description more --}}
                            <td>{{ Str::limit($report->user->name ?? ($report->vexpenses_user_integration_id ?? "-"), 15) }}</td>
                            <td class="text-end">R$ {{ number_format($report->amount, 2, ",", ".") }}</td>
                            <td>{{ \Carbon\Carbon::parse($report->report_date)->format("d/m/y") }}</td> {{-- Shorter date --}}
                            <td>
                                <span class="badge bg-{{ $report->status == "Pago" ? "success" : ($report->status == "Pendente" || $report->status == "Importado" ? "warning" : "secondary") }}">
                                    {{ $report->status }}
                                </span>
                            </td>
                            <td>{{ $report->origin }}</td>
                            <td>{{ $report->payment_date ? \Carbon\Carbon::parse($report->payment_date)->format("d/m/y") : "-" }}</td>
                            <td style="text-align: center;">
                                <button type="button" class="btn btn-xs btn-outline-primary view-expenses-btn mb-1" data-report-id="{{ $report->id }}" title="Ver Despesas">
                                    <i class="fas fa-eye"></i> <span class="d-none d-md-inline">Despesas</span>
                                </button>
                                @if($report->status !== "Pago")
                                    <a href="{{ route("financial_reports.markAsPaid", $report->id) }}" class="btn btn-xs btn-success mb-1 action-btn" title="Marcar como Pago">
                                        <i class="fas fa-check"></i> <span class="d-none d-md-inline">Pagar</span>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center">Nenhum relatório financeiro encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($financialReports->hasPages())
            <div class="card-footer py-2">
                {{-- Alterado para usar o template de paginação customizado --}}
                {{ $financialReports->links("vendor.pagination.custom-pagination") }}
            </div>
        @endif
    </div>
</div>

<!-- Modal Detalhes das Despesas -->
<div class="modal fade" id="expenseDetailsModal" tabindex="-1" aria-labelledby="expenseDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="expenseDetailsModalLabel">Detalhes das Despesas do Relatório #<span id="modalReportId"></span></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="loadingExpenses" class="text-center" style="display: none;">
          <div class="spinner-border" role="status">
            <span class="visually-hidden">Carregando...</span>
          </div>
          <p>Carregando despesas...</p>
        </div>
        <div id="noExpensesFound" class="text-center" style="display: none;">
          <p>Nenhuma despesa encontrada para este relatório.</p>
        </div>
        <table class="table table-sm table-striped" id="expenseDetailsTable" style="display: none;">
          <thead>
            <tr>
              <th>Título</th>
              <th>Data</th>
              <th class="text-end">Valor</th>
              <th>Observação</th>
              <th>Recibo</th>
            </tr>
          </thead>
          <tbody id="expenseDetailsTableBody">
            <!-- As linhas das despesas serão inseridas aqui pelo JavaScript -->
          </tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const expenseDetailsModalElement = document.getElementById("expenseDetailsModal");
    if (!expenseDetailsModalElement) {
        console.error("Elemento do modal #expenseDetailsModal não encontrado.");
        return;
    }

    const modalReportIdSpan = document.getElementById("modalReportId");
    const tableBody = document.getElementById("expenseDetailsTableBody");
    const expenseDetailsTable = document.getElementById("expenseDetailsTable");
    const loadingDiv = document.getElementById("loadingExpenses");
    const noExpensesDiv = document.getElementById("noExpensesFound");

    document.querySelectorAll(".view-expenses-btn").forEach(button => {
        button.addEventListener("click", function() {
            const reportId = this.dataset.reportId;
            if (modalReportIdSpan) modalReportIdSpan.textContent = reportId;
            if (tableBody) tableBody.innerHTML = ""; 
            
            if (loadingDiv) loadingDiv.style.display = "block";
            if (noExpensesDiv) noExpensesDiv.style.display = "none";
            if (expenseDetailsTable) expenseDetailsTable.style.display = "none";
            
            if (typeof $ !== "undefined") {
                $("#expenseDetailsModal").modal("show");
            } else {
                console.warn("jQuery não definido. Abertura do modal Bootstrap 4 pode não funcionar via JS puro sem atributos data-toggle/target.");
            }

            fetch(`/financial-reports/${reportId}/expenses`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Falha ao buscar despesas (Status: ${response.status})`);
                    }
                    return response.json();
                })
                .then(expenses => {
                    if (loadingDiv) loadingDiv.style.display = "none";
                    if (expenses && expenses.length > 0) {
                        if (expenseDetailsTable) expenseDetailsTable.style.display = "table";
                        if (tableBody) {
                            expenses.forEach(expense => {
                                let receiptLink = expense.receipt_url ? `<a href="${expense.receipt_url}" target="_blank" class="btn btn-sm btn-outline-secondary">Ver</a>` : "-";
                                tableBody.innerHTML += `
                                    <tr>
                                        <td>${expense.title ? expense.title : "-"}</td>
                                        <td>${expense.date}</td>
                                        <td class="text-end">R$ ${expense.value}</td>
                                        <td>${expense.observation ? expense.observation : "-"}</td>
                                        <td>${receiptLink}</td>
                                    </tr>
                                `;
                            });
                        }
                    } else {
                        if (noExpensesDiv) noExpensesDiv.style.display = "block";
                    }
                })
                .catch(error => {
                    if (loadingDiv) loadingDiv.style.display = "none";
                    if (noExpensesDiv) {
                        noExpensesDiv.style.display = "block";
                        noExpensesDiv.innerHTML = `<p class="text-danger">Erro ao carregar despesas: ${error.message}</p>`;
                    }
                    console.error("Erro ao buscar despesas:", error);
                });
        });
    });
});
</script>

@endsection



