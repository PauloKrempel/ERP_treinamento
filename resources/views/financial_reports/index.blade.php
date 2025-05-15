@extends("layouts.app")

@section("content")
<div class="container-fluid px-2"> {{-- Reduced padding for container-fluid --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>{{ $pageTitle ?? "Contas a Pagar (Relatórios Financeiros)" }}</h1>
        <div>
            <a href="{{ route("financial_reports.create") }}" class="btn btn-success btn-sm">Adicionar Relatório Manual</a>
            
            {{-- Botão para Puxar Novos Relatórios --}}
            <form action="{{ route("vexpenses.reports.import") }}" method="POST" class="d-inline ms-1">
                @csrf
                <button type="submit" class="btn btn-info btn-sm">Puxar Novos Relatórios do VExpenses</button>
            </form>

            {{-- Novo Botão para Atualizar Relatórios Existentes --}}
            <form action="{{ route("vexpenses.reports.updateExisting") }}" method="POST" class="d-inline ms-1">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm">Atualizar Relatórios Existentes do VExpenses</button>
            </form>
        </div>
    </div>

    {{-- Exibição de Mensagens Flash Detalhadas --}}
    @if(session("success_detailed"))
        <div class="alert alert-success">
            <strong>{{ session("success_detailed")["title"] }}</strong>
            @if(isset(session("success_detailed")["details"]) && is_array(session("success_detailed")["details"]))
                <ul>
                    @foreach(session("success_detailed")["details"] as $detail)
                        <li>{{ $detail }}</li>
                    @endforeach
                </ul>
            @elseif(isset(session("success_detailed")["details"]) && is_string(session("success_detailed")["details"]))
                 <p>{{ session("success_detailed")["details"] }}</p>
            @endif
        </div>
    @endif

    @if(session("error_detailed"))
        <div class="alert alert-danger">
            <strong>{{ session("error_detailed")["title"] }}</strong>
            @if(isset(session("error_detailed")["details"]) && is_array(session("error_detailed")["details"]))
                <ul>
                    @foreach(session("error_detailed")["details"] as $detail)
                        <li>{{ $detail }}</li>
                    @endforeach
                </ul>
            @elseif(isset(session("error_detailed")["details"]) && is_string(session("error_detailed")["details"]))
                 <p>{{ session("error_detailed")["details"] }}</p>
            @endif
        </div>
    @endif

    @if(session("warning_detailed"))
        <div class="alert alert-warning">
            <strong>{{ session("warning_detailed")["title"] }}</strong>
            @if(isset(session("warning_detailed")["details"]) && is_array(session("warning_detailed")["details"]))
                <ul>
                    @foreach(session("warning_detailed")["details"] as $detail)
                        <li>{{ $detail }}</li>
                    @endforeach
                </ul>
            @elseif(isset(session("warning_detailed")["details"]) && is_string(session("warning_detailed")["details"]))
                 <p>{{ session("warning_detailed")["details"] }}</p>
            @endif
        </div>
    @endif

    {{-- Mensagens Flash Simples (mantidas para compatibilidade ou outros usos) --}}
    @if(session("success") && !session("success_detailed"))
        <div class="alert alert-success">
            {{ session("success") }}
        </div>
    @endif
    @if(session("warning") && !session("warning_detailed"))
        <div class="alert alert-warning">
            {{ session("warning") }}
        </div>
    @endif
    @if(session("error") && !session("error_detailed"))
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
                                <span class="badge bg-{{ $report->status == "Pago" ? "success" : ($report->status == "Pendente" || $report->status == "Importado" || $report->status == "APROVADO" ? "warning" : "secondary") }}">
                                    {{ $report->status }}
                                </span>
                            </td>
                            <td>{{ $report->origin }}</td>
                            <td>{{ $report->payment_date ? \Carbon\Carbon::parse($report->payment_date)->format("d/m/y") : "-" }}</td>
                            <td style="text-align: center;">
                                <button type="button" class="btn btn-xs btn-outline-primary view-expenses-btn mb-1 action-btn" 
                                        data-report-id="{{ $report->id }}" 
                                        data-report-description="{{ Str::limit($report->description, 50) }}" 
                                        title="Ver Despesas">
                                    <i class="fas fa-eye"></i> <span class="d-none d-md-inline">Despesas</span>
                                </button>
                                @if($report->status !== "Pago")
                                    <form action="{{ route("financial_reports.markAsPaid", $report->id) }}" method="POST" class="d-inline action-btn" onsubmit="return confirm("Tem certeza que deseja marcar este relatório como pago?");">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-success mb-1" title="Marcar como Pago">
                                            <i class="fas fa-check"></i> <span class="d-none d-md-inline">Pagar</span>
                                        </button>
                                    </form>
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
                {{ $financialReports->appends(request()->query())->links("vendor.pagination.custom-pagination") }} {{-- Mantendo filtros na paginação --}}
            </div>
        @endif
    </div>
</div>

<!-- Modal Detalhes das Despesas (Bootstrap 4) -->
<div class="modal fade" id="expenseDetailsModal" tabindex="-1" role="dialog" aria-labelledby="expenseDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="expenseDetailsModalLabel">Detalhes das Despesas do Relatório #<span id="modalReportId"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h6 id="modalReportDescription" class="mb-3"></h6>
                <div id="loadingExpenses" class="text-center" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Carregando...</span> {{-- sr-only for Bootstrap 4 --}}
                    </div>
                    <p>Carregando despesas...</p>
                </div>
                <div id="noExpensesFound" class="text-center" style="display: none;">
                    <p>Nenhuma despesa encontrada para este relatório.</p>
                </div>
                <div id="errorLoadingExpenses" class="text-center text-danger" style="display: none;">
                    <p>Ocorreu um erro ao carregar as despesas. Tente novamente mais tarde.</p>
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
                        <!-- As linhas de despesa serão inseridas aqui pelo JavaScript -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push("styles")
<style>
    .action-btn {
        min-width: 80px; /* Ajuste conforme necessário */
        margin-bottom: 0.25rem; /* Espaçamento entre botões */
    }
    .table-sm th, .table-sm td {
        padding: 0.4rem; /* Ajuste o padding para células da tabela */
    }
</style>
@endpush

@push("scripts")
<script>
$(document).ready(function() { // jQuery document ready
    // Cache jQuery selectors
    const $modal = $("#expenseDetailsModal");
    const $modalReportIdSpan = $("#modalReportId");
    const $modalReportDescriptionElem = $("#modalReportDescription");
    const $tableBody = $("#expenseDetailsTableBody");
    const $expenseTable = $("#expenseDetailsTable");
    const $loadingDiv = $("#loadingExpenses");
    const $noExpensesDiv = $("#noExpensesFound");
    const $errorDiv = $("#errorLoadingExpenses");

    $(".view-expenses-btn").on("click", function() {
        const reportId = $(this).data("report-id");
        const reportDescription = $(this).data("report-description");
        
        $modalReportIdSpan.text(reportId);
        $modalReportDescriptionElem.text("Descrição: " + reportDescription);
        $tableBody.empty(); // Limpa despesas anteriores
        $expenseTable.hide();
        $noExpensesDiv.hide();
        $errorDiv.hide();
        $loadingDiv.show();
        
        $modal.modal("show"); // Show modal using jQuery for Bootstrap 4

        // Fetch expenses data (similar to original, but using the corrected route and expecting JSON object)
        fetch(`/financial-reports/${reportId}/expenses`) // Route está correta
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => { 
                        throw new Error(`Network response was not ok (${response.status} ${response.statusText}): ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                $loadingDiv.hide();
                if (data.expenses && data.expenses.length > 0) {
                    data.expenses.forEach(expense => {
                        const expenseDate = new Date(expense.expense_date).toLocaleDateString("pt-BR", { timeZone: "UTC" }); // Ensure date is parsed correctly
                        const expenseValue = parseFloat(expense.value).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
                        let receiptLink = "-";
                        if (expense.receipt_url) {
                            receiptLink = `<a href="${expense.receipt_url}" target="_blank" class="btn btn-xs btn-outline-info">Ver Recibo</a>`;
                        }

                        const row = `<tr>
                                        <td>${expense.title || "-"}</td>
                                        <td>${expenseDate}</td>
                                        <td class="text-end">${expenseValue}</td>
                                        <td>${expense.observation || "-"}</td>
                                        <td>${receiptLink}</td>
                                     </tr>`;
                        $tableBody.append(row);
                    });
                    $expenseTable.show();
                } else {
                    $noExpensesDiv.show();
                }
            })
            .catch(error => {
                console.error("Error fetching expenses:", error);
                $loadingDiv.hide();
                $errorDiv.text(`Erro ao carregar despesas: ${error.message}`).show();
            });
    });
});
</script>
@endpush

