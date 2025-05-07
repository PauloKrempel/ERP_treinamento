@extends("layouts.app")

@section("content")
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>{{ $pageTitle ?? "Detalhes do Usuário" }}</span>
                        <a href="{{ route("users.index") }}" class="btn btn-sm btn-secondary">Voltar para Lista</a>
                    </div>
                </div>

                <div class="card-body">
                    <h4>Informações do Usuário</h4>
                    <table class="table table-bordered table-sm">
                        <tbody>
                            <tr>
                                <th style="width: 25%;">ID</th>
                                <td>{{ $user->id }}</td>
                            </tr>
                            <tr>
                                <th>Nome</th>
                                <td>{{ $user->name }}</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td>{{ $user->email }}</td>
                            </tr>
                            <tr>
                                <th>ID VExpenses</th>
                                <td>{{ $user->vexpenses_id ?? "Não informado" }}</td>
                            </tr>
                            <tr>
                                <th>Total Pago ao Usuário</th>
                                <td><strong>R$ {{ number_format($user->total_paid_amount ?? 0, 2, ",", ".") }}</strong></td>
                            </tr>
                            <tr>
                                <th>Membro Desde</th>
                                <td>{{ $user->created_at->format("d/m/Y H:i") }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <hr>

                    <h4>Relatórios Pagos para {{ $user->name }}</h4>
                    @if($paidReports->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>ID Relatório</th>
                                        <th>Descrição</th>
                                        <th class="text-end">Valor Pago</th>
                                        <th>Data do Pagamento</th>
                                        <th>Data do Relatório</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($paidReports as $report)
                                        <tr>
                                            <td>{{ $report->id }}</td>
                                            <td>{{ Str::limit($report->description, 50) }}</td>
                                            <td class="text-end">R$ {{ number_format($report->amount, 2, ",", ".") }}</td>
                                            <td>{{ $report->payment_date ? \Carbon\Carbon::parse($report->payment_date)->format("d/m/Y H:i") : "-" }}</td>
                                            <td>{{ \Carbon\Carbon::parse($report->report_date)->format("d/m/Y") }}</td>
                                            <td>
                                                <a href="{{ route("financial_reports.index", ["search_report_id" => $report->id]) }}" class="btn btn-xs btn-outline-primary" target="_blank">Ver na Lista</a>
                                                {{-- Você pode adicionar um botão para ver detalhes do relatório se tiver uma view para isso --}}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($paidReports->hasPages())
                            <div class="mt-3">
                                {{ $paidReports->links() }}
                            </div>
                        @endif
                    @else
                        <p>Nenhum relatório pago encontrado para este usuário.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

