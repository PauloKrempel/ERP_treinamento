@extends("layouts.app")

@section("content")
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>{{ $pageTitle ?? "Contas a Pagar (Relatórios Financeiros)" }}</h1>
        <div>
            <a href="{{ route("financial_reports.create") }}" class="btn btn-success">Adicionar Relatório Manual</a>
            <form action="{{ route("vexpenses.reports.import") }}" method="POST" class="d-inline">
                @csrf
                {{-- Add date filters for import if desired --}}
                {{-- <label for="import_start_date">Data Inicial:</label>
                <input type="date" name="import_start_date" id="import_start_date">
                <label for="import_end_date">Data Final:</label>
                <input type="date" name="import_end_date" id="import_end_date"> --}}
                <button type="submit" class="btn btn-info">Puxar Relatórios do VExpenses</button>
            </form>
        </div>
    </div>

    @if(session("success"))
        <div class="alert alert-success">
            {{ session("success") }}
        </div>
    @endif
    @if(session("error"))
        <div class="alert alert-danger">
            {{ session("error") }}
        </div>
    @endif

    {{-- Filters for the local list --}}
    <form method="GET" action="{{ route("financial_reports.index") }}" class="mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="status" class="form-label">Status Local:</label>
                <select name="status" id="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach($statusOptions as $key => $value)
                        <option value="{{ $key }}" {{ request("status") == $key ? "selected" : "" }}>{{ $value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="origin" class="form-label">Origem:</label>
                <select name="origin" id="origin" class="form-select">
                    <option value="">Todas</option>
                     @foreach($originOptions as $key => $value)
                        <option value="{{ $key }}" {{ request("origin") == $key ? "selected" : "" }}>{{ $value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="start_date" class="form-label">Data Inicial:</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ request("start_date") }}">
            </div>
            <div class="col-md-2">
                <label for="end_date" class="form-label">Data Final:</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ request("end_date") }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">Lista de Relatórios</div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID Local</th>
                        <th>ID VExpenses</th>
                        <th>Descrição</th>
                        <th>Usuário</th>
                        <th>Valor</th>
                        <th>Data Relatório</th>
                        <th>Status Local</th>
                        <th>Origem</th>
                        <th>Data Pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($financialReports as $report)
                        <tr>
                            <td>{{ $report->id }}</td>
                            <td>{{ $report->vexpenses_report_id ?? "N/A" }}</td>
                            <td>{{ Str::limit($report->description, 50) }}</td>
                            <td>{{ $report->user->name ?? ($report->vexpenses_user_integration_id ?? "N/A") }}</td>
                            <td>R$ {{ number_format($report->amount, 2, ",", ".") }}</td>
                            <td>{{ \Carbon\Carbon::parse($report->report_date)->format("d/m/Y") }}</td>
                            <td><span class="badge bg-{{ $report->status == "Pago" ? "success" : ($report->status == "Pendente" || $report->status == "Importado" ? "warning" : "secondary") }}">{{ $report->status }}</span></td>
                            <td>{{ $report->origin }}</td>
                            <td>{{ $report->payment_date ? \Carbon\Carbon::parse($report->payment_date)->format("d/m/Y H:i") : "-" }}</td>
                            <td>
                                @if($report->status !== "Pago")
                                    <form action="{{ route("financial_reports.markAsPaid", $report->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">Marcar como Pago</button>
                                    </form>
                                @endif
                                @if($report->origin == "Manual")
                                   {{-- <a href="{{ route("financial_reports.edit", $report->id) }}" class="btn btn-sm btn-warning">Editar</a> --}}
                                   {{-- <form action="{{ route("financial_reports.destroy", $report->id) }}" method="POST" class="d-inline" onsubmit="return confirm("Tem certeza que deseja excluir este relatório manual?");">
                                        @csrf
                                        @method("DELETE")
                                        <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                                    </form> --}}
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
            <div class="card-footer">
                {{ $financialReports->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

