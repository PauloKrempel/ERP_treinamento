@extends("layouts.app")

@section("content")
<div class="container">
    <h1>{{ $pageTitle ?? "Adicionar Novo Relatório Financeiro (Manual)" }}</h1>

    @if(session("error"))
        <div class="alert alert-danger">
            {{ session("error") }}
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route("financial_reports.store") }}" method="POST">
        @csrf
        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <label for="description" class="form-label">Descrição <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="description" name="description" value="{{ old("description") }}" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="amount" class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="{{ old("amount") }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="report_date" class="form-label">Data do Relatório <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="report_date" name="report_date" value="{{ old("report_date", date("Y-m-d")) }}" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="user_id" class="form-label">Usuário (Opcional)</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <option value="">Nenhum</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old("user_id") == $user->id ? "selected" : "" }}>{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Status Inicial <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Pendente" {{ old("status") == "Pendente" ? "selected" : "" }}>Pendente</option>
                            <option value="Aprovado" {{ old("status") == "Aprovado" ? "selected" : "" }}>Aprovado</option>
                            {{-- Outros status podem ser adicionados se fizerem sentido para criação manual --}}
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Observações (Opcional)</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3">{{ old("notes") }}</textarea>
                </div>

            </div>
            <div class="card-footer text-end">
                <a href="{{ route("financial_reports.index") }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar Relatório Manual</button>
            </div>
        </div>
    </form>
</div>
@endsection

