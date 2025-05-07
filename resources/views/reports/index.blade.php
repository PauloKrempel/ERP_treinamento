@extends("layouts.app")

@section("content")
<div class="container">
    <div class="row mb-3">
        <div class="col-md-12">
            <h2>Relatórios VExpenses</h2>
        </div>
    </div>

    <form method="GET" action="{{ route("reports.index") }}" class="mb-4">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label for="status_id">Status</label>
                    <select name="status_id" id="status_id" class="form-control">
                        <option value="">Todos</option>
                        @foreach($statusOptions as $id => $name)
                            <option value="{{ $id }}" {{ (isset($filters["status_id"]) && $filters["status_id"] == $id) ? "selected" : "" }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="start_date">Data Inicial</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $filters["start_date"] ?? "" }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="end_date">Data Final</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $filters["end_date"] ?? "" }}">
                </div>
            </div>
            <div class="col-md-3 align-self-end">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="{{ route("reports.index") }}" class="btn btn-secondary">Limpar</a>
            </div>
        </div>
    </form>

    @if (empty($reports))
        <div class="alert alert-info">
            Nenhum relatório encontrado com os filtros aplicados.
        </div>
    @else
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID Relatório</th>
                    <th>Descrição</th>
                    <th>Usuário (Nome)</th>
                    <th>Data</th>
                    <th>Valor Total</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reports as $report)
                    <tr>
                        <td>{{ $report["id"] }}</td>
                        <td>{{ $report["description"] ?? "N/A" }}</td>
                        <td>{{ $report["user_name"] ?? ($report["user"]["name"] ?? "N/A") }}</td>
                        <td>{{ Carbon\Carbon::parse($report["date"])->format("d/m/Y") }}</td>
                        <td>R$ {{ number_format($report["amount"], 2, ",", ".") }}</td>
                        <td>
                            <span class="badge badge-{{
                                match($report["status_id"]) {
                                    1 => "secondary", // Aberto
                                    2 => "warning",   // Pendente
                                    3 => "success",   // Aprovado
                                    4 => "primary",   // Pago
                                    5 => "danger",    // Rejeitado
                                    default => "light"
                                }
                            }}">
                                {{ $statusOptions[$report["status_id"]] ?? "Desconhecido" }}
                            </span>
                        </td>
                        <td>
                            @if ($report["status_id"] == 3) <!-- Aprovado -->
                                <form action="{{ route("reports.markAsPaid", $report["id"]) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm("Confirmar pagamento deste relatório?")">Marcar como Pago</button>
                                </form>
                            @else
                                N/A
                            @endif
                            <!-- Adicionar link para ver detalhes do relatório no VExpenses se disponível -->
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <!-- Lógica de paginação se a API suportar ou se for implementada manualmente -->
    @endif
</div>
@endsection

